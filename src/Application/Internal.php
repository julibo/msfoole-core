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

use Julibo\Msfoole\Interfaces\Application;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;

class Internal implements Application
{
    public function __construct(SwooleRequest $request, SwooleResponse $response)
    {
        parent::__construct($request, $response);
    }

    public function init()
    {
        // TODO: Implement init() method.
    }

    public function handling()
    {
        // TODO: Implement handling() method.
    }

    public function destruct()
    {
        // TODO: Implement destruct() method.
    }
}