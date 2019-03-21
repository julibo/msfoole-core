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


interface Application
{

    public function __construct(SwooleRequest $request, SwooleResponse $response);

    public function init();

    public function handling();

    public function destruct();

}