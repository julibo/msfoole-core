<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole的简易微服务框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------

namespace Julibo\Msfoole\Server;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Websocket\Server as Websocket;
use Swoole\WebSocket\Frame as Webframe;
use Swoole\Process;
use Swoole\Table;
use Swoole\Coroutine as SwooleCoroutine;
use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Log;
use Julibo\Msfoole\Cache;
use Julibo\Msfoole\Channel;
use Julibo\Msfoole\Helper;
use Julibo\Msfoole\Interfaces\Server as BaseServer;

class HttpServer extends BaseServer
{
    /**
     * SwooleServer类型
     * @var string
     */
    protected $serverType = 'http';

    /**
     * 支持的响应事件
     * @var array
     */
    protected $event = [
        'Start',
        'Shutdown',
        'ManagerStart',
        'ManagerStop',
        'WorkerStart',
        'WorkerStop',
        'WorkerExit',
        'WorkerError',
        'Close',
        'Open',
        'Message',
        'Request'
    ];

    /**
     * 应用服务
     * @var
     */
    protected $app;

    /**
     * 客户端连接内存表
     */
    protected $table;

    /**
     * 全局缓存
     * @var Cache
     */
    protected $cache;

    /**
     * @var 通道
     */
    protected $chan;

    /**
     * 初始化
     */
    protected function init()
    {
        $this->option['upload_tmp_dir'] = TEMP_PATH;
        $this->option['http_parse_post'] = true;
        $this->option['http_compression'] = true;
        $config = Config::get('msfoole') ?? [];
        unset($config['host'], $config['port'], $config['ssl'], $config['option']);
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 启动辅助逻辑
     */
    protected function startLogic()
    {
        # 创建客户端连接内存表
        if ($this->serverType == 'socket') {
            $this->createTable();
        }
        # 开启全局缓存
        $cacheConfig = Config::get('cache.default') ?? [];
        $this->cache = new Cache($cacheConfig);
        # 开启异步定时监控
        $this->monitorProcess();
    }

    /**
     * 创建客户端连接内存表
     */
    private function createTable()
    {
        $this->table = new table($this->config['table']['size'] ?? 1024);
        $this->table->column('token', table::TYPE_STRING, 32);
        $this->table->column('counter', table::TYPE_INT, 4);
        $this->table->column('create_time', table::TYPE_INT, 4);
        $this->table->column('last_time', table::TYPE_INT, 4);
        $this->table->column('user_info', table::TYPE_STRING, 8092);
        $this->table->create();
    }

    /**
     * 文件监控，不包含配置变化
     * table内存表监控
     */
    private function monitorProcess()
    {
        $paths = $this->config['monitor']['path'] ?? null;
        if ($paths) {
            $monitor = new Process(function (Process $process) use ($paths) {
                // echo "文件监控进程启动";
                Helper::setProcessTitle("msfoole:monitor");
                $timer = $this->config['monitor']['interval'] ?? 10;
                swoole_timer_tick($timer * 1000, function () use($paths) {
                    if (!is_array($paths)) {
                        $paths = array($paths);
                    }
                    foreach ($paths as $path) {
                        $path = ROOT_PATH . $path;
                        if (!is_dir($path)) {
                            continue;
                        }
                        $dir      = new \RecursiveDirectoryIterator($path);
                        $iterator = new \RecursiveIteratorIterator($dir);
                        foreach ($iterator as $file) {
                            if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                                continue;
                            }
                            if ($this->lastMtime < $file->getMTime()) {
                                $this->lastMtime = $file->getMTime();
                                echo '[update]' . $file . " reload...\n";
                                $this->swoole->reload();
                                break 2;
                            }
                        }
                    }
                });
            });
            $this->swoole->addProcess($monitor);
        }
    }

    public function onStart(\Swoole\Server $server)
    {
        // echo "主进程启动";
        Helper::setProcessTitle("msfoole:master");
    }

    public function onShutdown(\Swoole\Server $server)
    {
        // echo "主进程结束";
        Helper::sendDingRobotTxt("主进程结束");
    }

    public function onManagerStart(\Swoole\Server $server)
    {
        // echo "管理进程启动";
        Helper::setProcessTitle("msfoole:manager");
    }

    public function onManagerStop(\Swoole\Server $server)
    {
        // echo "管理进程停止";
        Helper::sendDingRobotTxt("管理进程停止");
    }

    public function onWorkerStop(\Swoole\Server $server, int $worker_id)
    {
        // echo "worker进程终止";
        Helper::sendDingRobotTxt("worker进程终止");
    }

    public function onWorkerExit(\Swoole\Server $server, int $worker_id)
    {
        // echo "worker进程退出";
        Helper::sendDingRobotTxt("worker进程退出");
    }

    public function onWorkerError(\Swoole\Server $serv, int $worker_id, int $worker_pid, int $exit_code, int $signal)
    {
        $error = sprintf("worker进程异常:[%d] %d 退出的状态码为%d, 退出的信号为%d", $worker_pid, $worker_id, $exit_code, $signal);
        Helper::sendDingRobotTxt($error);
    }

    public function onClose(\Swoole\Server $server, int $fd, int $reactorId)
    {
        // 销毁内存表记录
        if (!is_null($this->table) && $this->table->exist($fd)) {
            $this->table->del($fd);
        }
    }

    /**
     * Worker进程启动回调
     * @param \Swoole\Server $server
     * @param int $worker_id
     */
    public function onWorkerStart(\Swoole\Server $server, int $worker_id)
    {
        echo "worker进程启动";
        // step 1 创建通道
        $chanConfig = $this->config['channel'];
        $capacity = $chanConfig['capacity'] ?? 100;
        $this->chan = Channel::instance($capacity);
        // step 2 开启日志通道
        Log::setChan($this->chan);
        // step 3 创建协程工作池
        $this->WorkingPool();
    }

    /**
     * 工作协程
     * 负责从通道中消费数据并进行异步处理
     */
    private function WorkingPool()
    {
        go(function () {
            while(1) {
                $data = $this->chan->pop();
                if (!empty($data) && is_int($data['type'])) {
                    switch ($data['type']) {
                        case 2:
                            // 执行自定义方法
                            if ($data['class'] && $data['method']) {
                                $parameter = $data['parameter'] ?? [];
                                call_user_func_array([$data['class'], $data['method']], $parameter);
                            }
                            break;
                        default:
                            // 写入日志记录
                            if (!empty($data['log'])) {
                                Log::saveData($data['log']);
                            }
                            break;
                    }
                }
            }
        });
    }

    /**
     * request回调
     * @param $request
     * @param $response
     */
    public function onRequest(SwooleRequest $request, SwooleResponse $response)
    {
        // 执行应用并响应
        // print_r($request);
    }

}
