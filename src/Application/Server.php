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
use Julibo\Msfoole\Helper;
use Julibo\Msfoole\Prompt;
use Julibo\Msfoole\HttpClient;
use Julibo\Msfoole\Exception\ServerException;
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
        Log::setEnv($this->httpRequest)->info('请求开始，请求参数为 {message}', ['message' => json_encode($this->httpRequest->params)]);
    }

    /**
     * 析构方法
     */
    public function destruct()
    {
        unset($this->cookie);
        $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
        $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
        Log::setEnv($this->httpRequest)->info('请求结束，执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
        if ($executionTime > Config::get('log.slow_time')) {
            Log::setEnv($this->httpRequest)->slow('当前方法执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
        }
    }

    /**
     * 服务解析
     * @return bool
     * @throws ServerException
     */
    private function resolve()
    {
        $whiteList = '';
        $serverKey = sprintf("%s:*", $this->httpRequest->serviceName);
        $serverList = Cache::hscan(Config::get('msfoole.machine.robotkey'), null, $serverKey, 10000);
        $regular = false;
        if (empty($serverList)) {
            throw new ServerException(Prompt::$server['SERVER_INVALID']['msg'], Prompt::$server['SERVER_INVALID']['code']);
        }
        foreach ($serverList as $vo) {
            if (Helper::isJson($vo) !== false) {
                $server = json_decode($vo, true);
                if ($server['power'] > 60) {
                    $whiteList = $server['white_list'] ?? '';
                    $regular = true;
                    break;
                }
            }
        }
        if (!$regular) { //　不存在有效的可用服务
            throw new ServerException(Prompt::$server['SERVER_INVALID']['msg'], Prompt::$server['SERVER_INVALID']['code']);
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
     * @throws ServerException
     */
    private function userReview()
    {
        $user = $this->cookie->getTokenCache();
        if (empty($user) || empty($user['power'])) {
            throw new ServerException(Prompt::$server['NOT_LOGIN']['msg'], Prompt::$server['NOT_LOGIN']['code']);
        }
        $demand1 = sprintf("%s.%s.%s", $this->httpRequest->modular, $this->httpRequest->controller, $this->httpRequest->action);
        $demand2 = sprintf("%s.%s.*", $this->httpRequest->modular, $this->httpRequest->controller);
        $demand3 = sprintf("%s.*.*", $this->httpRequest->modular);
        if ($user['power'] != '*') {
            if (!is_array($user['power']) || (!in_array($demand1, $user['power']) && !in_array($demand2, $user['power']) && !in_array($demand3, $user['power']))) {
                throw new ServerException(Prompt::$server['NOT_POWER']['msg'], Prompt::$server['NOT_POWER']['code']);
            }
        }
    }

    /**
     * 随机调用可用服务
     * @return mixed
     * @throws ServerException
     */
    private function selectServer()
    {
        $server = [];
        $serverKey = sprintf("%s:*", $this->httpRequest->serviceName);
        $serverList = Cache::hscan(Config::get('msfoole.machine.robotkey'), null, $serverKey, 10000);
        if (empty($serverList)) {
            throw new ServerException(Prompt::$server['SERVER_INVALID']['msg'], Prompt::$server['SERVER_INVALID']['code']);
        }
        foreach ($serverList as $vo) {
            if (Helper::isJson($vo) !== false) {
                $s = json_decode($vo, true);
                if ($s['power'] > 60) {
                    array_push($server, $s);
                }
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
            throw new ServerException(Prompt::$server['SERVER_INVALID']['msg'], Prompt::$server['SERVER_INVALID']['code']);
        }
    }

    /**
     * 调用子服务
     * @param $ip
     * @param $port
     * @param $permit
     * @return array
     * @throws ServerException
     */
    private function working($ip, $port, $permit)
    {
        $token = $this->cookie->getToken();
        $cli = new HttpClient($ip, $port, $permit, $this->httpRequest->identification, $token);
        $url = $this->httpRequest->getPathInfo();
        $method = $this->httpRequest->getRequestMethod();
        if (strtoupper($method) == 'POST') {
            $params = $this->httpRequest->params;
            $result = $cli->post($url, $params);
            return $result;
        } else if (strtoupper($method) == 'GET') {
            $str = $this->httpRequest->getQueryString();
            if ($str) {
                $url = sprintf('%s?%s', $url, $str);
            }
            $result = $cli->get($url);
            return $result;
        } else {
            throw new ServerException(Prompt::$common['WAY_NOT_ALLOW']['msg'], Prompt::$common['WAY_NOT_ALLOW']['code']);
        }

    }

    /**
     * @throws ServerException
     */
    private function checkRequest()
    {
        $header = $this->httpRequest->getHeader();
        if (empty($header) || empty($header['level']) || empty($header['timestamp']) || empty($header['token']) ||
            empty($header['signstr']) || $header['level'] != 2 || $header['timestamp'] > strtotime('10 minutes') * 1000 ||
            $header['timestamp'] < strtotime('-10 minutes') * 1000) {
            throw new ServerException(Prompt::$common['REQUEST_EXCEPTION']['msg'], Prompt::$common['REQUEST_EXCEPTION']['code']);
        }
        $signsrc = $header['timestamp'].$header['token'];
        if (!empty($this->params)) {
            ksort($this->params);
            array_walk($this->params, function($value,$key) use (&$signsrc) {
                $signsrc .= $key.$value;
            });
        }
        if (md5($signsrc) != $header['signstr']) {
            throw new ServerException(Prompt::$common['SIGN_EXCEPTION']['msg'], Prompt::$common['SIGN_EXCEPTION']['code']);
        }
        return $this;
    }

    /**
     * @throws ServerException
     */
    private function checkToken()
    {
        $token = $this->httpRequest->getHeader('token');
        if (empty($token)) {
            throw new ServerException(Prompt::$common['UNLAWFUL_TOKEN']['msg'], Prompt::$common['UNLAWFUL_TOKEN']['code']);
        } else {
            $default = Config::get('application.default.key');
            if ($default != $token) {
                $user = $this->cookie->getTokenCache($token);
                if (empty($user)) {
                    throw new ServerException(Prompt::$common['UNLAWFUL_TOKEN']['msg'], Prompt::$common['UNLAWFUL_TOKEN']['code']);
                }
            }
        }
        return $this;
    }

    /**
     * 处理http请求
     */
    public function handling()
    {
        try {

            # step 0 验证请求合法性
            // $this->checkToken()->checkRequest();
            # step 1 查找对应的服务
            $freedom = $this->resolve();
            # step 2 在需要认证的服务里进行用户权限认证
            if (!$freedom) {
                $this->userReview();
            }
            # step 3 选择可用服务
            $server = $this->selectServer();
            # step 4 调用服务
            ob_start();
            $data = $this->working($server['ip'], $server['port'], $server['permit']);
            # step 5 封装结果
            $this->packingResult($data);
            # step 6 输出渲染
            $content = ob_get_clean();
            $this->httpResponse->end($content);
        } catch (\Throwable $e) {
            if (Config::get('application.debug')) {
                $content = ['code' => $e->getCode(), 'msg'=>$e->getMessage(), 'identification' => $this->httpRequest->identification,
                    'extra'=>['file'=>$e->getFile(), 'line'=>$e->getLine()]];
            } else {
                $content = ['code' => $e->getCode(), 'msg'=>$e->getMessage(), 'identification' => $this->httpRequest->identification];
            }
            $this->httpResponse->end(json_encode($content));
            if ($e->getCode() >= 1000) {
                throw $e;
            }
        }
    }

    /**
     * 解释器
     * @param array $data
     * @throws ServerException
     */
    private function explain(array $data)
    {
        if (isset($data['code']) && $data['code'] == 0) {
            if (Config::get('application.debug')) {
                $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
                $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
                $result = ['code' => 0, 'data' => $data['data'], 'identification' => $this->httpRequest->identification,
                    'executionTime' =>$executionTime, 'consumeMem' => $consumeMem ];
            } else {
                $result = ['code' => 0, 'data' => $data['data'], 'identification' => $this->httpRequest->identification];
            }
            echo json_encode($result);
        } else {
            if (empty($data["extra"])) {
                throw new ServerException($data['msg'], $data['code']);
            } else {
                throw new ServerException($data['msg'], $data['code'], $data['extra']['file'], $data['extra']['line']);
            }
        }
    }

    /**
     * @param array $response
     * @return string
     * @throws ServerException
     */
    private function packingResult(array $response)
    {
        if ($response['errCode'] != 0 ) {
            $msg = socket_strerror($response['errCode']);
            throw new ServerException($msg, Prompt::$common['SYSTEM_ERROR']['code']);
        } else {
            switch ($response['statusCode']) {
                case 200:
                    if (Helper::isJson($response['data']) === false) { // 字符串直接输出
                        echo $response['data'];
                    } else {
                        $data = json_decode($response['data'], true);
                        $this->explain($data);
                    }
                    break;
                case 404:
                    throw new ServerException(Prompt::$common['OTHER_ERROR']['msg'], Prompt::$common['OTHER_ERROR']['code']);
                    break;
                default:
                    throw new ServerException(Prompt::$common['FORCED_DISCONN']['msg'], Prompt::$common['FORCED_DISCONN']['code']);
                    break;
            }
        }
    }

}
