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
        Log::setEnv($this->httpRequest)->info('请求开始，请求参数为 {message}', ['message' => json_encode($this->httpRequest->params)]);
    }

    /**
     * 析构方法
     */
    protected function destruct()
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
            $permit = $this->httpRequest->getHeader('permit');
            if (empty($server[$s]['permit']) || $server[$s]['permit'] != $permit) {
                throw new ServerException(Prompt::$server['REQUEST_EXCEPTION']['msg'], Prompt::$server['REQUEST_EXCEPTION']['code']);
            }
            return $server[$s];
        } else {
            throw new ServerException(Prompt::$server['SERVER_INVALID']['msg'], Prompt::$server['SERVER_INVALID']['code']);
        }
    }

    /**
     * 调用服务
     * @param $ip
     * @param $port
     * @param $permit
     * @return mixed
     */
    private function working($ip, $port, $permit) : array
    {
        $token = $this->cookie->getToken();
        $identification = $this->httpRequest->identification;
        $cli = new HttpClient($ip, $port, $permit, $identification, $token);
        $url = $this->httpRequest->getPathInfo();
        $params = $this->httpRequest->params ?? [];
        $response = $cli->post($url, $params);
        return $response;
    }

    /**
     * 处理http请求
     */
    public function handling()
    {
        try {
            ob_start();
            # step 1 选择可用服务
            $server = $this->selectServer();
            # step 2 调用服务
            $response = $this->working($server['ip'], $server['port'], $server['permit']);
            # step 3 封装结果
            $this->packingData($response);
            # step 4 输出渲染
            $content = ob_get_clean();
            $this->httpResponse->end($content);
        } catch (\Throwable $e) {
            if ($e->getCode() == 0) {
                $code = 911;
            } else {
                $code = $e->getCode();
            }
            if (Config::get('application.debug')) {
                $content = ['code' => $code, 'msg'=>$e->getMessage(), 'identification' => $this->httpRequest->identification,
                    'extra'=>['file'=>$e->getFile(), 'line'=>$e->getLine()]];
            } else {
                $content = ['code' => $code, 'msg'=>$e->getMessage(), 'identification' => $this->httpRequest->identification];
            }
            $this->httpResponse->end(json_encode($content));
            if ($e->getCode() >= 1000) {
                throw $e;
            }
        }
    }

    /**
     * @param array $response
     * @throws ServerException
     */
    private function packingData(array $response)
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

}
