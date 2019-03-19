<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole的多进程API服务框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------

namespace Julibo\Msfoole;

use Swoole\Http\Response as SwooleResponse;

class Response
{
    private $swooleResponse;

    public function __construct(SwooleResponse $response)
    {
        $this->swooleResponse = $response;
    }

    /**
     * 设置HTTP响应的Header信息
     * @param string $key
     * @param string $value
     * @param bool $ucwords
     */
    public function header(string $key, string $value, bool $ucwords = true)
    {
        $this->swooleResponse->header($key, $value, $ucwords);
    }

    /**
     * 设置HTTP响应的cookie信息
     * @param string $key
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     */
    public function cookie(string $key, string $value = '', int $expire = 0 , string $path = '/', string $domain='', bool $secure = false, bool $httponly = false)
    {
        $this->swooleResponse->cookie($key, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * 发送Http状态码
     * @param int $http_status_code
     */
    public function status(int $http_status_code)
    {
        $this->swooleResponse->status($http_status_code);
    }

    /**
     * 发送Http跳转
     * @param string $url
     * @param int $http_code
     */
    public function redirect(string $url, int $http_code = 302)
    {
        $this->swooleResponse->redirect($url, $http_code);
    }

    /**
     * 发送文件到浏览器
     * @param string $filename
     * @param int $offset
     * @param int $length
     */
    public function sendfile(string $filename, int $offset = 0, int $length = 0)
    {
        $this->swooleResponse->sendfile($filename, $offset, $length);
    }

    /**
     * 发送Http响应体，并结束请求处理
     * @param string $html
     */
    public function end(string $html)
    {
        $this->swooleResponse->end($html);
    }
}
