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

namespace Julibo\Msfoole;

use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Cache;

class Cookie
{

    /**
     * @var 请求cookie
     */
    private $cookies;

    /**
     * http响应对象
     * @var
     */
    private $response;

    /**
     * @var 请求header
     */
    private $header;

    /**
     * 默认配置
     * @var
     */
    private $config = [
        // cookie 名称前缀
        'prefix'    => '',
        // cookie 保存时间
        'expire'    => 0,
        // cookie 保存路径
        'path'      => '/',
        // cookie 有效域名
        'domain'    => '',
        //  cookie 启用安全传输
        'secure'    => false,
        // httponly设置
        'httponly'  => '',
        // 用户标识
        'token' => 'TOKEN',
        // 自动授时临界值
        'auto_selling' => 600,
        // 缓存前缀
        'cache_prefix' => 'user:'
    ];

    /**
     * 初始化
     * @param HttpRequest $request
     * @param Response $response
     */
    public function init(HttpRequest $request, Response $response)
    {
        $this->config = array_merge($this->config, Config::get('cookie'));
        $this->response = $response;
        $this->cookies = $request->getCookie();
        $this->header = $request->getHeader();
    }

    /**
     * 设置cookie
     * @param string $key
     * @param string $value
     * @param int $expire
     */
    public function setCookie($key, $value = '', int $expire = 0)
    {
        $key = $this->config['prefix'] . $key;
        if ($expire == 0) {
            $expire = $this->config['expire'] + time();
        } else {
            $expire = time() + $expire;
        }
        $expire = strtotime('+8 hours', $expire);
        $path = $this->config['path'] ?: '/';
        $domain = $this->config['domain'] ?: '';
        $secure = $this->config['secure'] ? true : false;
        $httponly = $this->config['httponly'] ? true : false;
        $this->response->cookie($key, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * 获取cookie
     * @param string $key
     * @return string|null
     */
    public function getCookie($key)
    {
        $key = $this->config['prefix'] . $key;
        return $this->cookies[$key] ?? null;
    }

    /**
     * 获取用户token
     * @return string|null
     */
    public function getToken()
    {
        $token = $this->getCookie($this->config['token']);
        if (!$token) {
            $token = $this->header['token'] ?? null;
        }
        return $token;
    }

    /**
     * 设置用户token
     * @param array $user
     * @param null $uuid
     */
    public function setToken(array $user = [], $uuid = null)
    {
        $uuid = $uuid ?? Helper::guid();
        $this->setCookie($this->config['token'], $uuid);
        Cache::set($this->config['cache_prefix'] . $uuid, $user, $this->config['expire']);
    }


    /**
     * 获取用户缓存
     * @param null $token
     * @return array|null
     */
    public function getTokenCache($token = null)
    {
        if (!$token) {
            $cookie = true;
            $token = $this->getToken();
        } else {
            $cookie = false;
        }
        $user = Cache::get($this->config['cache_prefix'] . $token);
        if ($user) {
            $deadline = Cache::getPeriod($token);
            if ($deadline < $this->config['auto_selling']) {
                if ($cookie) {
                    $this->setToken($user, $token);
                } else {
                    Cache::set($this->config['cache_prefix'].$token, $user, $this->config['expire']);
                }
            }
            return $user;
        } else {
            return null;
        }
    }

}
