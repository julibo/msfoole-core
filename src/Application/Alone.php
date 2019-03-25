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

use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Cookie;
use Julibo\Msfoole\Facade\Log;
use Julibo\Msfoole\HttpRequest;
use Julibo\Msfoole\Response;
use Julibo\Msfoole\Loader;
use Julibo\Msfoole\Exception;
use Julibo\Msfoole\Prompt;
use Julibo\Msfoole\Interfaces\Application;

class Alone extends Application
{

    public function init()
    {
        $this->httpRequest->init();
        $this->httpRequest->explain();
        Cookie::init($this->httpRequest, $this->httpResponse);
        Log::setEnv($this->httpRequest);
    }

    /**
     * 运行请求
     * @throws Exception
     */
    public function working()
    {
        $controller = Loader::factory($this->httpRequest->controller, $this->httpRequest->namespace, $this->httpRequest);
        if(!is_callable(array($controller, $this->httpRequest->action))) {
            throw new Exception(Prompt::$common['METHOD_NOT_EXIST']['msg'], Prompt::$common['METHOD_NOT_EXIST']['code']);
        }
        $data = call_user_func([$controller, $this->httpRequest->action]);
        if ($data === null && ob_get_contents() != '') {
        } else if (is_string($data) && is_array(Config::get('application.allow.output')) && in_array($data, Config::get('application.allow.output'))) {
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
            echo $e->getMessage();
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
