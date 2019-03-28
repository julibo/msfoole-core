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
use Julibo\Msfoole\Channel;

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
     * @var 通道
     */
    protected $chan;

    /**
     * Application constructor.
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @param Channel $chan
     */
    public function __construct(SwooleRequest $request, SwooleResponse $response, Channel $chan)
    {
        $this->beginTime = microtime(true);
        $this->beginMem  = memory_get_usage();
        $this->chan = $chan;
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
        $this->destruct();
        unset($this->httpRequest, $this->httpResponse, $this->chan);
    }

}