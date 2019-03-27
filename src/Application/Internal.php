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

use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Cache;
use Julibo\Msfoole\Facade\Log;
use Julibo\Msfoole\Cookie;
use Julibo\Msfoole\Exception;
use Julibo\Msfoole\Prompt;
use Julibo\Msfoole\HttpClient;
use Julibo\Msfoole\Interfaces\Application;

class Internal extends Application
{
    private $cookie;

    /**
     * 初始化
     */
    protected function init()
    {
        $this->httpRequest->init();
        $this->httpRequest->resolve();
        $this->cookie = new Cookie($this->httpRequest, $this->httpResponse);
    }

    /**
     * 析构方法
     */
    public function destruct()
    {
        $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
        $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
        Log::setEnv($this->httpRequest)->info('请求结束，执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
        if ($executionTime > Config::get('log.slow_time')) {
            Log::setEnv($this->httpRequest)->slow('当前方法执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
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
            if (count($server) == 1) {
                $s = 0;
            } else {
                $tmp = [];
                foreach ($server as $k => $v) {
                    for($i = 0; $i < $v['power']; $i++) {
                        $tmp[] = $k;
                    }
                }
                $seed = array_rand($tmp);
                $s = $tmp[$seed];
            }
            return $server[$s];
        } else {
            throw new Exception(Prompt::$server['SERVER_INVALID']['msg'], Prompt::$server['SERVER_INVALID']['code']);
        }
    }

    /**
     * 调用服务
     * @param $ip
     * @param $port
     * @param $permit
     * @return mixed
     */
    private function working($ip, $port, $permit)
    {
        $token = Cookie::getToken();
        $identification = $this->httpRequest->identification;
        $cli = new HttpClient($ip, $port, $permit, $identification, $token);
        $url = $this->httpRequest->getPathInfo();
        $params = $this->httpRequest->params;
        $cli->postDefer($url, $params);
        $cli->recv();
        $result = $cli->answer();
        return $result;
    }

    /**
     * 处理http请求
     */
    public function handling()
    {
        try {
            # step 1 选择可用服务
            $server = $this->selectServer();
            # step 2 调用服务
            $data = $this->working($server['ip'], $server['port'], $server['permit']);
            var_dump($data);
            # step 3 封装结果
            $result = "<h1>Hello Swoole. #".rand(1000, 9999)."</h1>";
            $this->httpResponse->end($result);
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }

}
