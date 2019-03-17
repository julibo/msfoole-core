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
use Swoole\Process;
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
     * 注册端口
     * @var int
     */
    private $regPort = 9222;

    /**
     * 健康检查key
     * @var string
     */
    private $robotkey = 'regMachine';

    /**
     * 服务端IP
     * @var string
     */
    private $server_ip = '127.0.0.1';

    /**
     * 服务端端口
     * @var string
     */
    private $server_port = '9111';


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
        'Request'
    ];

    /**
     * 应用服务
     * @var
     */
    protected $app;

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
        if ($this->pattern == 2) {
            if (!empty($config['server']['regport'])) {
                $this->regPort =  $config['server']['regport'];
            }
            if (!empty($config['server']['robotkey'])) {
                $this->robotkey =  $config['server']['robotkey'];
            }
        } else if ($this->pattern == 1){
            if (!empty($config['client']['ip'])) {
                $this->server_ip =  $config['client']['ip'];
            }
            if (!empty($config['client']['port'])) {
                $this->server_port =  $config['client']['port'];
            }
        }
        unset($config['host'], $config['port'], $config['ssl'], $config['option']);
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 启动辅助逻辑
     */
    protected function startLogic()
    {
        # 开启全局缓存
        $cacheConfig = Config::get('cache.default') ?? [];
        $this->cache = new Cache($cacheConfig);
        # 开启异步定时监控
        $this->monitorProcess();
        # 开启注册监听
        if ($this->pattern == 2) {
            $reg_server = $this->swoole->addListener($this->host, $this->regPort, SWOOLE_SOCK_TCP);
            $reg_server->on("request", [$this, "regMachine"]);
            # 开启健康监测
            $this->monitorHealth();
        }
    }

    /**
     * 健康监测
     */
    private function monitorHealth()
    {
        $monitor = new Process(function (Process $process) {
            // echo "健康监测进程启动";
            Helper::setProcessTitle("msfoole:health");
            swoole_timer_tick(60000, function () {
                $robot = $this->cache->HGETALL($this->robotkey);
                $clients = [];
                foreach ($robot as $k=>$vo) {
                    $server = json_decode($vo, true);
                    $cli = new \Swoole\Coroutine\Http\Client($server['ip'], $server['port']);
                    $cli->setHeaders([
                        'Host' => "localhost",
                        "User-Agent" => 'Chrome/49.0.2587.3',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml',
                        'Accept-Encoding' => 'gzip',
                    ]);
                    $cli->set([ 'timeout' => 1]);
                    $cli->setDefer();
                    $cli->get('/Index/Index/health');
                    $clients[$k] = $cli;
                }
                foreach ($clients as $k => $cli) {
                    $server = $this->cache->hget($this->robotkey, $k);
                    $server = json_decode($server, true);
                    $server['counter'] = $server['counter']++;
                    $cli->recv();
                    if ($cli->body) {
                        if ($server['power'] < 100) {
                            $server['power'] = $server['power']++;
                        }
                        $server['living'] = time();
                        $this->cache->HSET($this->robotkey, $k, json_encode($server));
                    } else {
                        $server['power'] = $server['power'] - 20;
                        if ($server['power'] <= 0) {
                            $this->cache->HDEL($this->robotkey, $k);
                        } else {
                            $this->cache->HSET($this->robotkey, $k, json_encode($server));
                        }
                    }
                }
            });
        });
        $this->swoole->addProcess($monitor);
    }

    /**
     * 文件监控，不包含配置变化
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
        echo "主进程启动";
        Helper::setProcessTitle("msfoole:master");
        // 客户端启动进行服务注册
        if ($this->pattern == 1) {
            $application = Config::get('application') ?? [];
            $cli = new \Swoole\Coroutine\Http\Client($this->server_ip, $this->server_port);
            $cli->setHeaders([
                'Host' => "localhost",
                "User-Agent" => 'Chrome/49.0.2587.3',
                'Accept' => 'text/html,application/xhtml+xml,application/xml',
                'Accept-Encoding' => 'gzip',
            ]);
            $cli->set([ 'timeout' => 3]);
            $params = array(
                'server' => $application['name'],
                'ip' => $application['ip'],
                'port' => $application['port'],
                'version' => $application['version'],
                'timestamp' => time()
            );
            $regSigner = $this->regSigner($params);
            $params['signer'] = $regSigner;
            $cli->post('/', $params);
            echo $cli->body;
            $cli->close();
        }
    }

    public function onShutdown(\Swoole\Server $server)
    {
        // echo "主进程结束";
        $tips = sprintf("【%s:%s】主进程结束", $this->host, $this->port);
        Helper::sendDingRobotTxt($tips);
    }

    public function onManagerStart(\Swoole\Server $server)
    {
        // echo "管理进程启动";
        Helper::setProcessTitle("msfoole:manager");
    }

    public function onManagerStop(\Swoole\Server $server)
    {
        // echo "管理进程停止";
        $tips = sprintf("【%s:%s】管理进程停止", $this->host, $this->port);
        Helper::sendDingRobotTxt($tips);
    }

    public function onWorkerStop(\Swoole\Server $server, int $worker_id)
    {
        // echo "worker进程终止";
        $tips = sprintf("【%s:%s】worker进程终止", $this->host, $this->port);
        Helper::sendDingRobotTxt($tips);
    }

    public function onWorkerExit(\Swoole\Server $server, int $worker_id)
    {
        // echo "worker进程退出";
        $tips = sprintf("【%s:%s】worker进程退出", $this->host, $this->port);
        Helper::sendDingRobotTxt($tips);
    }

    public function onWorkerError(\Swoole\Server $serv, int $worker_id, int $worker_pid, int $exit_code, int $signal)
    {
        $error = sprintf("worker进程异常:[%d] %d 退出的状态码为%d, 退出的信号为%d", $worker_pid, $worker_id, $exit_code, $signal);
        Helper::sendDingRobotTxt($error);
    }

    public function onClose(\Swoole\Server $server, int $fd, int $reactorId)
    {
        // echo sprintf('%s的连接关闭', $fd);
    }

    /**
     * Worker进程启动回调
     * @param \Swoole\Server $server
     * @param int $worker_id
     */
    public function onWorkerStart(\Swoole\Server $server, int $worker_id)
    {
        // echo "worker进程启动";
        Helper::setProcessTitle("msfoole:worker");
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
            while(true) {
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
     * 注册签名
     * @param array $params
     * @return string
     */
    private function regSigner(array $params)
    {
        ksort($params);
        $string = implode('', $params);
        return strtoupper(substr(md5($string), 8, 16));
    }

    /**
     * 注册机回调
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     */
    public function regMachine(SwooleRequest $request, SwooleResponse $response)
    {
//        $res = $this->cache->hscan('regMachine', null, 'test*', 100);
        $result = 0;
        if ($request->post) {
            $server = $request->post['server'] ?? '';
            $ip = $request->post['ip'] ?? '';
            $port = $request->post['port'] ?? '';
            $version = $request->post['version'] ?? '';
            $timestamp = $request->post['timestamp'] ?? '';
            $signer = $request->post['signer'] ?? '';
            if ($server && $ip && $port && $version && $timestamp && $signer) {
                $regSigner = $this->regSigner([
                    'server' => $server,
                    'ip' => $ip,
                    'port' => $port,
                    'version' => $version,
                    'timestamp' => $timestamp,
                ]);
                if ($regSigner == $signer) {
                    $field = $server .':'.$ip.':'.$port;
                    $robot = [
                        'server' => $server,
                        'ip' => $ip,
                        'port' => $port,
                        'version' => $version,
                        'create_time' => date('Y-m-d H:i:s'),
                        'power' => 100,
                        'counter' => 0,
                        'living' => time(),
                    ];
                    $this->cache->HSET($this->robotkey, $field, json_encode($robot));
                    $result = 1;
                }
            }
        }
        $response->end($result);
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
        if ($this->pattern == 2) {
            // 服务端网关
            $response->end("<h1>Hello Swoole Server. #".rand(1000, 9999)."</h1>");
        } else if ($this->pattern == 1) {
            // 客户端
            $response->end("<h1>Hello Swoole Client. #".rand(1000, 9999)."</h1>");
        } else {
            // 独立端
            $response->end("<h1>Hello Swoole Alone. #".rand(1000, 9999)."</h1>");
        }
    }

}
