<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole4的简易微服务框架 ]
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
use Julibo\Msfoole\Loader;
use Julibo\Msfoole\Exception;
use Julibo\Msfoole\Prompt;
use Julibo\Msfoole\Interfaces\Application;

class Alone implements Application
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
        $this->httpRequest = new HttpRequest($request);
        $this->httpRequest->init();
        $this->httpRequest->explain();
        $this->httpResponse = new Response($response);
        Cookie::init($this->httpRequest, $this->httpResponse);
        Log::setEnv($this->httpRequest);
    }

    public function init()
    {

    }

    /**
     * 运行请求
     * @throws Exception
     */
    public function working()
    {
        $controller = Loader::factory($this->httpRequest->controller, $this->httpRequest->namespace, $this->httpRequest);
        if(!is_callable(array($controller, $this->httpRequest->action))) {
            throw new Exception(Prompt::$exception['METHOD_NOT_EXIST']['msg'], Prompt::$exception['METHOD_NOT_EXIST']['code']);
        }
        $data = call_user_func([$controller, $this->httpRequest->action]);
        if ($data === null && ob_get_contents() != '') {
        } else if (is_string($data) && ((is_array(Config::get('application.allow.output')) && in_array($data, Config::get('application.allow.output'))) ||
            (is_string(Config::get('application.allow.output')) && $data == Config::get('application.allow.output')))) {
            echo $data;
        } else {
            if (Config::get('application.debug')) {
                $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
                $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
                $result = ['code' => 0, 'msg' => '', 'data' => $data, 'identification' => $this->httpRequest->identification, 'executionTime' =>$executionTime, 'consumeMem' => $consumeMem ];
            } else {
                $result = ['code' => 0, 'msg' => '', 'data' => $data, 'identification' => $this->httpRequest->identification];
            }
            echo json_encode($result);
        }
    }

    /**
     * 处理http请求
     */
    public function handling()
    {
        try {
             ob_start();
             Log::info('请求开始，请求参数为 {message}', ['message' => json_encode($this->httpRequest->params)]);
             $this->working();
             $content = ob_get_clean();
            $this->httpResponse->end($content);
        } catch (\Throwable $e) {
            if ($e->getCode() == 301 || $e->getCode() == 302) {
                $this->httpResponse->redirect($e->getMessage(), $e->getCode());
            }  else {
                $identification = $this->httpRequest->identification ?? '';
                if (Config::get('application.debug')) {
                    $content = ['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'identification' => $identification, 'extra'=>['file'=>$e->getFile(), 'line'=>$e->getLine()]];
                } else {
                    $content = ['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'identification' => $identification];
                }
                $this->httpResponse->end(json_encode($content));
                if ($e->getCode() >= 1000) {
                    throw $e;
                }
            }
        }
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

}
