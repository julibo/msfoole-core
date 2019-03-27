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
    protected $request;

    /**
     * @var
     */
    private $cookie;

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
     * 依赖注入HttpRequest
     * ClientController constructor.
     * @param HttpRequest $request
     * @param Cookie $cookie
     */
    public function __construct(HttpRequest $request, Cookie $cookie)
    {
        $this->request = $request;
        $this->cookie = $cookie;
        $this->params = $this->request->params;
        $this->header = $this->request->getHeader();
        $this->getUserByToken();
        $this->init();
    }


    /**
     * 通过token获取用户信息
     */
    private function getUserByToken()
    {
        $token =  $this->header['token'] ?? null;
        if ($token) {
            $user = $this->cookie->getTokenCache($token);
            $this->user = $user;
        }
    }

    /**
     * 初始化方法
     * @return mixed
     */
    abstract protected function init();

}
