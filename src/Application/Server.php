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

namespace Julibo\Msfoole\Application;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Cookie;
use Julibo\Msfoole\Facade\Cache;
use Julibo\Msfoole\Facade\Log;
use Julibo\Msfoole\HttpRequest;
use Julibo\Msfoole\Response;
use Julibo\Msfoole\Exception;
use Julibo\Msfoole\Prompt;
use Julibo\Msfoole\Interfaces\Application;

class Server implements Application
{
    /**
     * http请求
     * @var
     */
    private $httpRequest;

    /**
     * http应答
     * @var
     */
    private $httpResponse;

    // 开始时间和内存占用
    private $beginTime;
    private $beginMem;

    /**
     * 构造函数
     * Alone constructor.
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     */
    public function __construct(SwooleRequest $request, SwooleResponse $response)
    {
        $this->beginTime = microtime(true);
        $this->beginMem  = memory_get_usage();
        $this->httpResponse = new Response($response);
        $this->httpRequest = new HttpRequest($request);
        $this->init();
    }

    /**
     * 初始化
     */
    public function init()
    {
        $this->httpRequest->init();
        $this->httpRequest->resolve();
        Cookie::init($this->httpRequest, $this->httpResponse);
        Log::setEnv($this->httpRequest);
    }

    /**
     * 析构方法
     */
    public function destruct()
    {
        $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
        $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
        Log::info('请求结束，执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
        if ($executionTime > Config::get('log.slow_time')) {
            Log::slow('当前方法执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
        }
    }

    /**
     * 服务解析
     * @throws Exception
     */
    private function resolve()
    {
        $whiteList = '';
        $serverKey = sprintf("%s:*", $this->httpRequest->serviceName);
        $serverList = Cache::hscan(Config::get('msfoole.machine.robotkey'), null, $serverKey, 10000);
        $regular = false;
        foreach ($serverList as $vo) {
            $server = json_decode($vo, true);
            if ($server['power'] > 60) {
                $whiteList = $server['white_list'] ?? '';
                $regular = true;
                break;
            }
        }
        if (!$regular) { //　不存在有效的可用服务
            throw new Exception(Prompt::$server['SERVER_INVALID']['msg'], Prompt::$server['SERVER_INVALID']['code']);
        }
        // 判断是否为白名单请求
        if (!$whiteList) {
            return false;
        } else if ($whiteList == "*") {
            return true;
        } else {
            $mainList = explode(',', $whiteList);
            foreach ($mainList as $vo) {
                $list = explode('.', $vo);
                if ($list[0] != $this->httpRequest->modular) {
                    continue;
                } else {
                    if ($list[1] == '*') {
                        return true;
                    } else if ($list[1] == $this->httpRequest->controller) {
                        if ($list[2] == '*') {
                            return true;
                        } else if ($list[2] == $this->httpRequest->action) {
                            return true;
                        } else {
                            return false;
                        }
                    } else {
                        return false;
                    }
                }
            }
        }
    }

    /**
     * 用户鉴权
     * @throws Exception
     */
    private function userReview()
    {
        $user = Cookie::getTokenCache();
        if (empty($user) || empty($user['power'])) {
            throw new Exception(Prompt::$server['NOT_LOGIN']['msg'], Prompt::$server['NOT_LOGIN']['code']);
        }
        $demand = sprintf("%s.%s.%s", $this->httpRequest->modular, $this->httpRequest->controller, $this->httpRequest->action);
        if (!is_array($user['power']) || !in_array($demand, $user['power'])) {
            throw new Exception(Prompt::$server['NOT_POWER']['msg'], Prompt::$server['NOT_POWER']['code']);
        }
    }

    /**
     * 随机调用可用服务
     * @return mixed
     * @throws Exception
     */
    private function selectServer()
    {
        $server = [];
        $serverKey = sprintf("%s:*", $this->httpRequest->serviceName);
        $serverList = Cache::hscan(Config::get('msfoole.machine.robotkey'), null, $serverKey, 10000);
        foreach ($serverList as $vo) {
            $s = json_decode($vo, true);
            if ($s['power'] > 60) {
                array_push($server, $s);
            }
        }
        if ($server) {
            $tmp = [];
            foreach ($server as $k => $v) {
                for($i = 0; $i < $v['power']; $i++) {
                    $tmp[] = $k;
                }
            }
            $seed = array_rand($tmp);
            return $server[$tmp[$seed]];
        } else {
            throw new Exception(Prompt::$server['SERVER_INVALID']['msg'], Prompt::$server['SERVER_INVALID']['code']);
        }
    }

    /**
     * 调用服务
     * @param $ip
     * @param $port
     * @return mixed
     */
    public function working($ip, $port)
    {
        $cli = new \Swoole\Coroutine\Http\Client($ip, $port);
        $cli->setHeaders([
            'Host' => "localhost",
            "User-Agent" => 'Chrome/49.0.2587.3',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
        ]);
        $cli->set([ 'timeout' => 1]);
        $cli->get($this->httpRequest->getPathInfo());
        $result = $cli->body;
        $cli->close();
        return $result;
    }

    /**
     * 处理http请求
     */
    public function handling()
    {
        try {
            # step 1 查找对应的服务
            $freedom = $this->resolve();
            # step 2 在需要认证的服务里进行用户权限认证
            if ($freedom) {
                $this->userReview();
            }

            # step 3 选择可用服务
            $server = $this->selectServer();
            # step 4 调用服务
            $data = $this->working($server['ip'], $server['port']);

            # step 5 封装结果
            $result = "<h1>Hello Swoole. #".rand(1000, 9999)."</h1>";

            $this->httpResponse->end($result);
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }

}
