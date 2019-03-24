<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole4的简易微服务API框架 ]
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
use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Log;
use Julibo\Msfoole\Facade\Cache;
use Julibo\Msfoole\Channel;
use Julibo\Msfoole\Helper;
use Julibo\Msfoole\Application\Alone as AloneApplication;
use Julibo\Msfoole\Application\Client as ClientApplication;
use Julibo\Msfoole\Application\Server as ServerApplication;
use Julibo\Msfoole\Application\Internal as InternalApplication;
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
    protected $regPort = 9222;

    /**
     * 内部调用端口
     * @var int
     */
    protected $callPort = 9333;

    /**
     * 健康检查key
     * @var string
     */
    protected $robotkey = 'regMachine';

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
     * @var 通道
     */
    protected $chan;
    
    protected $permit;

    /**
     * 初始化
     */
    protected function init()
    {
        $this->option['upload_tmp_dir'] = TEMP_PATH;
        $this->option['http_parse_post'] = false;
        $this->option['http_compression'] = true;
        $config = Config::get('msfoole') ?? [];
        if ($this->pattern == 2) {
            if (!empty($config['machine']['regport'])) {
                $this->regPort =  $config['machine']['regport'];
            }
            if (!empty($config['machine']['robotkey'])) {
                $this->robotkey =  $config['machine']['robotkey'];
            }
            if (!empty($config['call']['port'])) {
                $this->callPort =  $config['call']['port'];
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
        # 初始化缓存
        $cacheConfig = Config::get('cache.default') ?? [];
        Cache::init($cacheConfig);
        # 开启异步定时监控
        $this->monitorProcess();
        # 开启注册监听
        if ($this->pattern == 2) {
            $reg_server = $this->swoole->addListener($this->host, $this->regPort, SWOOLE_SOCK_TCP);
            $reg_server->on("request", [$this, "regMachine"]);
            $reg_server = $this->swoole->addListener($this->host, $this->callPort, SWOOLE_SOCK_TCP);
            $reg_server->on("request", [$this, "callServer"]);
            # 开启健康监测
            // $this->monitorHealth();
        } else if ($this->pattern == 1) {
            $this->permit = Helper::guid();
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
                $robot = Cache::HGETALL($this->robotkey);
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
                    $cli->get($server['health_uri']);
                    $clients[$k] = $cli;
                }
                foreach ($clients as $k => $cli) {
                    $server = Cache::HGET($this->robotkey, $k);
                    $server = json_decode($server, true);
                    $server['counter'] = $server['counter']++;
                    $cli->recv();
                    if ($cli->body) {
                        if ($server['power'] < 100) {
                            $server['power'] = $server['power']++;
                        }
                        $server['living'] = time();
                        Cache::HSET($this->robotkey, $k, json_encode($server));
                    } else {
                        $server['power'] = $server['power'] - 20;
                        if ($server['power'] <= 0) {
                            Cache::HDEL($this->robotkey, $k);
                        } else {
                            Cache::HSET($this->robotkey, $k, json_encode($server));
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

    /**
     * @param \Swoole\Server $server
     */
    public function onStart(\Swoole\Server $server)
    {
        // echo "主进程启动";
        Helper::setProcessTitle("msfoole:master");
        // 客户端启动进行服务注册
        if ($this->pattern == 1) {
            $application = Config::get('application');
            $sidecar = Config::get('sidecar');
            $cli = new \Swoole\Coroutine\Http\Client($sidecar['server_ip'], $sidecar['server_port']);
            $cli->setHeaders([
                'Host' => "localhost",
                "User-Agent" => 'Chrome/49.0.2587.3',
                'Accept' => 'text/html,application/xhtml+xml,application/xml',
                'Accept-Encoding' => 'gzip',
            ]);
            $cli->set([ 'timeout' => 3]);
            $params = array(
                'server' => strtolower($application['name']),
                'ip' => $sidecar['ip'],
                'port' => $sidecar['port'],
                'version' => $application['version'],
                'timestamp' => time(),
                'health_uri' => $sidecar['health_uri'],
                'white_list' => $sidecar['white_list'] ?? ''
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
//        $res = Cache::hscan('regMachine', null, 'test*', 100);
        $result = 0;
        if ($request->post) {
            $server = $request->post['server'] ?? '';
            $ip = $request->post['ip'] ?? '';
            $port = $request->post['port'] ?? '';
            $version = $request->post['version'] ?? '';
            $timestamp = $request->post['timestamp'] ?? '';
            $signer = $request->post['signer'] ?? '';
            $health_uri = $request->post['health_uri'] ?? '';
            $white_list = $request->post['white_list'] ?? '';
            if ($server && $ip && $port && $version && $timestamp && $signer && $health_uri) {
                $regSigner = $this->regSigner([
                    'server' => $server,
                    'ip' => $ip,
                    'port' => $port,
                    'version' => $version,
                    'timestamp' => $timestamp,
                    'health_uri' => $health_uri,
                    'white_list' => $white_list
                ]);
                if ($regSigner == $signer) {
                    $field = $server .':'.$ip.':'.$port;
                    $robot = [
                        'server' => $server,
                        'ip' => $ip,
                        'port' => $port,
                        'version' => $version,
                        'health_uri' => $health_uri,
                        'white_list' => $white_list,
                        'create_time' => date('Y-m-d H:i:s'),
                        'power' => 100,
                        'counter' => 0,
                        'living' => time(),
                    ];
                    Cache::HSET($this->robotkey, $field, json_encode($robot));
                    $result = 1;
                }
            }
        }
        $response->end($result);
    }

    /**
     * 跨服务调用 不需要鉴权
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     */
    public function callServer(SwooleRequest $request, SwooleResponse $response)
    {
        $this->app = new InternalApplication($request, $response);
        $this->app->handling();
    }

    /**
     * request回调
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @throws \Throwable
     */
    public function onRequest(SwooleRequest $request, SwooleResponse $response)
    {
        // 执行应用并响应
        // print_r($request);
        $uri = $request->server['request_uri'];
        if ($uri == '/favicon.ico') {
            $response->status(404);
            $response->end();
        } else {
            // todo 浏览器跨域

            switch ($this->pattern) {
                case 2:
                    $this->serverRuning($request, $response);
                    break;
                case 1:
                    $this->clientRuning($request, $response);
                    break;
                default:
                    $this->aloneRuning($request, $response);
                    break;
            }
        }
    }

    /**
     * 服务端网关
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @throws \Throwable
     */
    private function serverRuning(SwooleRequest $request, SwooleResponse $response)
    {
        var_dump($this->permit);
        $this->app = new ServerApplication($request, $response);
        $this->app->handling();


    }

    /**
     * 客户端
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     */
    private function clientRuning(SwooleRequest $request, SwooleResponse $response)
    {
        $this->app = new ClientApplication($request, $response);
        $this->app->handling();


    }

    /**
     * 独立端
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @throws \Throwable
     */
    private function aloneRuning(SwooleRequest $request, SwooleResponse $response)
    {
        $this->app = new AloneApplication($request, $response);
        $this->app->handling();
        $this->app->destruct();
    }

}
