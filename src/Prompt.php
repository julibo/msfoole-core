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

namespace Julibo\Msfoole;

class Prompt
{
    public static $common = [ // 900
        'METHOD_NOT_EXIST' => ['code' => 998, 'msg' => '请求方法不存在'],
        'REQUEST_EXCEPTION' => ['code' => 980, 'msg' => '非法请求'],
        'SIGN_EXCEPTION' => ['code' => 981, 'msg' => '签名错误'],
        'AUTH_EXCEPTION' => ['code' => 982, 'msg' => '用户认证失败'],
    ];


    public static $server = [ // 800
        'SERVER_INVALID' => ['code' => 899, 'msg' => '该服务目前无法正常响应'],
        'NOT_LOGIN' => ['code' => 880, 'msg' => '用户未登录'],
        'NOT_POWER' => ['code' => 880, 'msg' => '用户未登录'],
    ];

    public static $exception = [ //
        'METHOD_NOT_EXIST' => ['code' => 998, 'msg' => '请求方法不存在'],
        'REQUEST_EXCEPTION' => ['code' => 980, 'msg' => '非法请求'],
        'SIGN_EXCEPTION' => ['code' => 981, 'msg' => '签名错误'],
        'AUTH_EXCEPTION' => ['code' => 982, 'msg' => '用户认证失败'],
    ];



}
