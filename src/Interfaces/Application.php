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

namespace Julibo\Msfoole\Interfaces;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Julibo\Msfoole\HttpRequest;
use Julibo\Msfoole\Response;


abstract class Application
{
    /**
     * http请求
     * @var
     */
    protected $httpRequest;

    /**
     * http应答
     * @var
     */
    protected $httpResponse;

    // 开始时间和内存占用
    protected $beginTime;
    protected $beginMem;

    /**
     * 构造函数
     * Application constructor.
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
     * @return mixed
     */
    abstract protected function init();

    /**
     * 业务操作
     * @return mixed
     */
    abstract public function handling();

    /**
     * 释放资源
     * @return mixed
     */
    abstract protected function destruct();

    /**
     * 释放资源
     */
    public function __destruct()
    {
        unset($this->httpRequest, $this->httpResponse);
        $this->destruct();
    }

}