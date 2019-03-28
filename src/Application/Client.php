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
use Julibo\Msfoole\Facade\Log;
use Julibo\Msfoole\Cookie;
use Julibo\Msfoole\Exception;
use Julibo\Msfoole\Prompt;
use Julibo\Msfoole\Loader;
use Julibo\Msfoole\Interfaces\Application;

class Client extends Application
{
    /**
     * @var
     */
    private $cookie;

    /**
     * @return mixed|void
     */
    public function init()
    {
        $this->httpRequest->init();
        $this->httpRequest->explain();
        $this->cookie = new Cookie($this->httpRequest, $this->httpResponse);
        Log::setEnv($this->httpRequest)->info('请求开始，请求参数为 {message}',
            ['message' => json_encode($this->httpRequest->params)]);
    }

    /**
     * @return mixed|void
     */
    protected function destruct()
    {
        unset($this->cookie);
        $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
        $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
        Log::setEnv($this->httpRequest)->info('请求结束，执行时间{executionTime}，消耗内存{consumeMem}',
            ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
        if ($executionTime > Config::get('log.slow_time')) {
            Log::setEnv($this->httpRequest)->slow('当前方法执行时间{executionTime}，消耗内存{consumeMem}',
                ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
        }
    }

    /**
     * @return mixed|void
     * @throws \Throwable
     */
    public function handling()
    {
        try {
            ob_start();
            $this->working();
            $content = ob_get_clean();
            $this->httpResponse->end($content);
        } catch (\Throwable $e) {
            if ($e->getCode() == 0) {
                $code = 911;
            } else {
                $code = $e->getCode();
            }
            if (Config::get('application.debug')) {
                $content = ['code'=>$code, 'msg'=>$e->getMessage(), 'identification' => $this->httpRequest->identification,
                    'extra'=>['file'=>$e->getFile(), 'line'=>$e->getLine()]];
            } else {
                $content = ['code'=>$code, 'msg'=>$e->getMessage(), 'identification' => $this->httpRequest->identification];
            }
            $this->httpResponse->end(json_encode($content));
            if ($e->getCode() >= 1000) {
                throw $e;
            }
        }
    }

    /**
     * 运行请求
     * @throws Exception
     */
    private function working()
    {
        $controller = Loader::factory($this->httpRequest->controller, $this->httpRequest->namespace, $this->httpRequest, $this->cookie, $this->chan);
        if(!is_callable(array($controller, $this->httpRequest->action))) {
            throw new Exception(Prompt::$common['METHOD_NOT_EXIST']['msg'], Prompt::$common['METHOD_NOT_EXIST']['code']);
        }
        $data = call_user_func([$controller, $this->httpRequest->action]);
        if ($data === null && ob_get_contents() != '') {
        } else {
            if (Config::get('application.debug')) {
                $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
                $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
                $result = ['code' => 0, 'data' => $data, 'identification' => $this->httpRequest->identification,
                    'executionTime' =>$executionTime, 'consumeMem' => $consumeMem];
            } else {
                $result = ['code' => 0, 'data' => $data, 'identification' => $this->httpRequest->identification];
            }
            echo json_encode($result);
        }
    }

}
