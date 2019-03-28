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

abstract class ClientController
{
    /**
     * @var
     */
    private $request;

    /**
     * @var
     */
    private $cookie;

    /**
     * @var
     */
    private $header;

    /**
     * @var
     */
    protected $params;

    /**
     * @var
     */
    protected $user;

    /**
     * @var
     */
    protected $token;

    /**
     * @var
     */
    protected $permit;

    /**
     * @var
     */
    protected $identification;

    /**
     * 依赖注入HttpRequest
     * ClientController constructor.
     * @param HttpRequest $request
     * @param Cookie $cookie
     */
    public function __construct(HttpRequest $request, Cookie $cookie)
    {
        $this->request = $request;
        $this->cookie = $cookie;
        $this->header = $this->request->getHeader();
        $this->permit = $this->header['permit'] ?? null;
        $this->identification = $this->header['identification'] ?? null;
        $this->params = $this->request->params;
        $this->getUserByToken();
        $this->init();
    }

    /**
     * @return mixed|null
     */
    protected function getPermit()
    {
        return $this->permit;
    }

    /**
     * @return mixed|null
     */
    protected function getIdentification()
    {
        return $this->identification;
    }

    /**
     * @return mixed
     */
    protected function getToken()
    {
        return $this->token;
    }

    /**
     * @return mixed
     */
    protected function getParams()
    {
        return $this->params;
    }

    /**
     * @return mixed
     */
    protected function getUser()
    {
        return $this->user;
    }

    /**
     * 通过token获取用户信息
     */
    private function getUserByToken()
    {
        $this->token =  $this->header['token'] ?? null;
        if ($this->token) {
            $user = $this->cookie->getTokenCache($this->token);
            $this->user = $user;
        }
    }

    /**
     * 初始化方法
     * @return mixed
     */
    abstract protected function init();

}
