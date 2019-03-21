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

use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Cookie;

abstract class AloneController
{
    /**
     * @var HttpRequest
     */
    protected $request;

    /**
     * @var
     */
    protected $params;

    /**
     * @var
     */
    protected $header;


    protected $user;

    /**
     * 依赖注入HttpRequest
     * HttpController constructor.
     * @param $request
     * @throws \Exception
     */
    final public function __construct(HttpRequest $request)
    {
        $this->request = $request;
        $this->params = $this->request->params;
        $this->header = $this->request->getHeader();
        $this->authentication();
        $this->init();
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
            if (!isset($this->header['level']) || !in_array($this->header['level'], [0, 1, 2]) || empty($this->header['timestamp']) || empty($this->header['token']) ||
                empty($this->header['signstr']) || !in_array($this->header['level'], [0, 1, 2]) ||
                $this->header['timestamp'] > strtotime('10 minutes') * 1000 ||
                $this->header['timestamp'] < strtotime('-10 minutes') * 1000) {
                throw new Exception(Prompt::$exception['REQUEST_EXCEPTION']['msg'], Prompt::$exception['REQUEST_EXCEPTION']['code']);
            }
            $supply = $this->header['timestamp'] . $this->header['token'];
            if (!empty($this->params)) {
                ksort($this->params);
                array_walk($this->params, function($value,$key) use (&$supply) {
                    $supply .= $key.$value;
                });
            }
            if (md5($supply) != $this->header['signstr']) {
                throw new Exception(Prompt::$exception['SIGN_EXCEPTION']['msg'], Prompt::$exception['SIGN_EXCEPTION']['code']);
            }

            $user = $this->getUserByToken();
            if ($user) {
                $this->user = $user;
            } else {
                throw new Exception(Prompt::$exception['AUTH_EXCEPTION']['msg'], Prompt::$exception['AUTH_EXCEPTION']['code']);
            }
        }
    }

    /**
     * 通过token获取用户信息
     * @return array
     */
    protected function getUserByToken() : array
    {
        $token =  $this->request->getHeader('token') ?? null;
        $user = Cookie::getTokenCache($token);
        return $user ?? [];
    }

    /**
     * 向客户端授权
     * @param array $user
     */
    protected function setToken(array $user)
    {
        Cookie::setToken($user);
    }

    /**
     * 初始化方法
     * @return mixed
     */
    abstract public function init();


}
