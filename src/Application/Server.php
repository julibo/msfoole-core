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

class Server extends Application
{
    /**
     * @var
     */
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
        $user = $this->cookie->getTokenCache();
        if (empty($user) || empty($user['power'])) {
            throw new Exception(Prompt::$server['NOT_LOGIN']['msg'], Prompt::$server['NOT_LOGIN']['code']);
        }
        $demand1 = sprintf("%s.%s.%s", $this->httpRequest->modular, $this->httpRequest->controller, $this->httpRequest->action);
        $demand2 = sprintf("%s.%s.*", $this->httpRequest->modular, $this->httpRequest->controller);
        $demand3 = sprintf("%s.*.*", $this->httpRequest->modular);
        if ($user['power'] != '*') {
            if (!is_array($user['power']) || (!in_array($demand1, $user['power']) && !in_array($demand2, $user['power']) && !in_array($demand3, $user['power']))) {
                throw new Exception(Prompt::$server['NOT_POWER']['msg'], Prompt::$server['NOT_POWER']['code']);
            }
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
        $token = $this->cookie->getToken();
        $identification = $this->httpRequest->identification;
        $cli = new HttpClient($ip, $port, $permit, $identification, $token);
        $method = strtolower($this->httpRequest->getRequestMethod());
        $url = $this->httpRequest->getPathInfo();
        $params = $this->httpRequest->params;
        $result = $cli->$method($url, $params);
        return $result;
    }

    /**
     * 处理http请求
     */
    public function handling()
    {
        try {
            Log::setEnv($this->httpRequest)->info('请求开始，请求参数为 {message}', ['message' => json_encode($this->httpRequest->params)]);
            # step 1 查找对应的服务
            $freedom = $this->resolve();
            # step 2 在需要认证的服务里进行用户权限认证
            if (!$freedom) {
                $this->userReview();
            }
            # step 3 选择可用服务
            $server = $this->selectServer();

            # step 4 调用服务
            $data = $this->working($server['ip'], $server['port'], $server['permit']);
            var_dump($data);

            # step 5 封装结果
            $result = "<h1>Hello Swoole. #".rand(1000, 9999)."</h1>";

            $this->httpResponse->end($result);
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }

}
