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

namespace Julibo\Msfoole\Application;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Cookie;
use Julibo\Msfoole\Facade\Log;
use Julibo\Msfoole\HttpRequest;
use Julibo\Msfoole\Response;

class Alone
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
     * 异常信息
     * @var array
     */
    private static $error = [
        'AUTH_FAILED' => ['code' => 20, 'msg' => '认证失败'],
        'CON_EXCEPTION' => ['code' => 21, 'msg' => '连接异常'],
        'REQUEST_EXCEPTION' => ['code' => 22, 'msg' => '非法请求'],
        'SIGN_EXCEPTION' => ['code' => 23, 'msg' => '签名异常'],
        'AUTH_EXCEPTION' => ['code' => 24, 'msg' => '用户认证失败'],
        'METHOD_NOT_EXIST' => ['code' => 29, 'msg' => '请求方法不存在'],
    ];

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
        $this->httpRequest = new HttpRequest($request);
        $this->httpResponse = new Response($response);
        Cookie::init($this->httpRequest, $this->httpResponse);
        Log::setEnv($this->httpRequest->identification, $this->httpRequest->request_method, $this->httpRequest->request_uri, $this->httpRequest->remote_addr);
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
     * 处理http请求
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     */
    public function Handling(SwooleRequest $request, SwooleResponse $response)
    {
        try {
            // ob_start();
            Log::info('请求开始，请求参数为 {message}', ['message' => json_encode($this->httpRequest->params)]);
            $response->end("<h1>Hello Swoole. #".rand(1000, 9999)."</h1>");
        } catch (\Throwable $e) {

        }
    }

    /**
     * 处理http请求
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @throws \Throwable
     */
    public function swooleHttp(SwooleRequest $request, SwooleResponse $response)
    {
        try {
            ob_start();
            $this->httpRequest = new HttpRequest($request);
            $this->httpResponse = new Response($response);
            Cookie::init($this->httpRequest, $this->httpResponse, $this->cache);
            Log::setEnv($this->httpRequest->identification, $this->httpRequest->request_method, $this->httpRequest->request_uri, $this->httpRequest->remote_addr);
            Log::info('请求开始，请求参数为 {message}', ['message' => json_encode($this->httpRequest->params)]);
            $this->working($this->httpRequest->identification);
            $content = ob_get_clean();
            $this->httpResponse->end($content);
        } catch (\Throwable $e) {
            if ($e->getCode() == 401) {
                $this->httpResponse->status(401);
                $this->httpResponse->end($e->getMessage());
            } else if ($e->getCode() == 301 || $e->getCode() == 302) {
                $this->httpResponse->redirect($e->getMessage(), $e->getCode());
            }  else {
                $identification = $this->httpRequest->identification ?? '';
                if (Config::get('application.debug')) {
                    $content = ['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'identification' => $identification, 'extra'=>['file'=>$e->getFile(), 'line'=>$e->getLine()]];
                } else {
                    $content = ['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'identification' => $identification];
                }
                $this->httpResponse->end(json_encode($content));
                if ($e->getCode() >= 500) {
                    throw $e;
                }
            }
        }
    }
    /**
     * 运行请求
     * @param null $identification
     * @throws Exception
     */
    private function working($identification =  null)
    {
        $controller = Loader::factory($this->httpRequest->controller, $this->httpRequest->namespace, $this->httpRequest, $this->cache);
        if(!is_callable(array($controller, $this->httpRequest->action))) {
            throw new Exception(self::$error['METHOD_NOT_EXIST']['msg'], self::$error['METHOD_NOT_EXIST']['code']);
        }
        $data = call_user_func([$controller, $this->httpRequest->action]);
        if ($data === null && ob_get_contents() != '') {
        } else if (is_string($data) && is_array(Config::get('application.allow.output')) && in_array($data, Config::get('application.allow.output'))) {
            echo $data;
        } else {
            if (Config::get('application.debug')) {
                $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
                $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
                $result = ['code' => 0, 'msg' => '', 'data' => $data, 'identification' => $identification, 'executionTime' =>$executionTime, 'consumeMem' => $consumeMem ];
            } else {
                $result = ['code' => 0, 'msg' => '', 'data' => $data, 'identification' => $identification];
            }
            echo json_encode($result);
        }
    }

}