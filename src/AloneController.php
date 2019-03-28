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

abstract class AloneController
{
    /**
     * @var HttpRequest
     */
    protected $request;

    /**
     * @var
     */
    protected $cookie;

    /**
     * @var Channel
     */
    protected $chan;

    /**
     * @var
     */
    protected $params;

    /**
     * @var
     */
    protected $header;

    /**
     * @var
     */
    protected $user;

    /**
     * @var
     */
    protected $token;

    /**
     * AloneController constructor.
     * @param $request
     * @param $cookie
     * @param $chan
     * @throws Exception
     */
    final public function __construct($request, $cookie, $chan)
    {
        $this->request = $request;
        $this->cookie = $cookie;
        $this->chan = $chan;
        $this->header = $this->request->getHeader();
        $this->params = $this->request->params;
        $this->authentication();
        $this->init();
    }

    /**
     * 初始化方法
     * @return mixed
     */
    abstract public function init();

    /**
     * 通过token获取用户信息
     * @return array
     */
    protected function getUserByToken()
    {
        $this->token =  $this->header['token'] ?? null;
        if ($this->token) {
            $this->user = $this->cookie->getTokenCache($this->token);
        }
        return $this->user;
    }

    /**
     * 向客户端授权
     * @param array $user
     */
    protected function setToken(array $user)
    {
        $this->cookie->setToken($user);
    }

    /**
     * 用户鉴权
     */
    final protected function authentication()
    {
        $execute = true;
        $allow = Config::get('application.allow.controller');
        if (is_array($allow)) {
            if (in_array(static::class, $allow)) {
                $execute = false;
            }
        } else {
            if (static::class == $allow) {
                $execute = false;
            }
        }
        // 需要鉴权
        if ($execute) {
            $user = $this->getUserByToken();
            if ($user) {
                $this->user = $user;
            } else {
                throw new Exception("用户认证失败", 666);
            }
        }
    }
}
