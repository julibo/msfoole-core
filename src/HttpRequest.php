<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole的简易微服务框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------

namespace Julibo\Msfoole;

use Swoole\Http\Request as SwooleRequest;
use Julibo\Msfoole\Facade\Config;

class HttpRequest
{

    /**
     * # 过滤器
     * @var mixed
     */
    private $filter;

    /**
     * # http请求原始报文
     */
    private $data;

    /**
     * # Http请求的头部信息，格式为数组。
     * @var
     */
    private $header;

    /**
     * Http请求相关的服务器信息，相当于PHP的$_SERVER数组
     * @var
     */
    private $server;

    /**
     * Http请求的GET参数，相当于PHP中的$_GET，格式为数组。
     * @var
     */
    private $get;

    /**
     * HTTP POST参数，格式为数组
     * @var
     */
    private $post;

    /**
     * HTTP请求携带的COOKIE信息，与PHP的$_COOKIE相同，格式为数组。
     * @var
     */
    private $cookie;

    /**
     * 获取原始的POST包体，用于非application/x-www-form-urlencoded格式的Http POST请求。
     * @var
     */
    private $input;

    /**
     * @var #
     */
    private $host;

    private $baseHost;

    /**
     * @var #
     */
    private $origin;

    /**
     * @var #
     */
    private $request_method;

    /**
     * @var #
     */
    private $request_uri;

    /**
     * @var #
     */
    private $path_info;

    /**
     * @var #
     */
    private $query_string;

    /**
     * @var #
     */
    private $remote_addr;

    /**
     * 请求唯一识别码 #
     * @var
     */
    private $identification;

    /**
     * @var array
     */
    private $config = [
        // 默认全局过滤方法 用逗号分隔多个
        'default_filter' => '',
    ];

    /**
     * 命名空间
     * @var
     */
    public $namespace;

    /**
     * 控制器
     * @var
     */
    public $controller;

    /**
     * 方法名
     * @var
     */
    public $action;

    /**
     * 请求参数
     * @var
     */
    public $params = [];

    /**
     * 构造方法
     * HttpRequest constructor.
     * @param SwooleRequest $request
     * @param array $options
     */
    public function __construct(SwooleRequest $request, array $options = [])
    {
        $this->config = array_merge($this->config, $options);

        if (is_null($this->filter) && !empty($this->config['default_filter'])) {
            $this->filter = $this->config['default_filter'];
        }

        $this->withData($request->getData())
            ->withHeader($request->header)
            ->withServer($request->server);

        if (isset($request->get))
            $this->withGet($request->get);
        if (isset($request->post))
            $this->withPost($request->post);
        if (isset($request->cookie))
            $this->withCookie($request->cookie);
        $rawContent = $request->rawContent();
        if (!empty($rawContent)) {
            $this->withInput($rawContent);
        }
        $this->explain();
    }

    /**
     * 获取完整原始报文
     * @param $data
     * @return $this
     */
    private function withData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Http请求的头部信息。类型为数组，所有key均为小写。
     * @param $header
     * @return $this
     */
    private function withHeader($header)
    {
        $this->header = $header;
        $this->host = $this->header['host'] ?? null;
        $this->origin = $this->header['origin'] ?? null;
        $this->identification = $this->header['identification_code'] ?? Helper::guid();
        if (isset($this->header['x-forwarded-for'])) {
            $remoteAddr = explode(', ', $this->header['x-forwarded-for']);
            $this->remote_addr = $remoteAddr[0];
        } else if (isset($this->header['x-real-ip'])) {
            $this->remote_addr = $this->header['x-real-ip'];
        }
        // $this->baseHost = Helper::getRootRegion($this->host);
        return $this;
    }

    /**
     * Http请求相关的服务器信息，相当于PHP的$_SERVER数组。
     * 包含了Http请求的方法，URL路径，客户端IP等信息。
     * @param $server
     * @return $this
     */
    private function withServer($server)
    {
        $this->server = $server;
        $this->request_method = $this->server['request_method'] ?? null;
        $this->request_uri = $this->server['request_uri'] ?? null;
        $this->path_info = $this->server['path_info'] ?? null;
        $this->query_string = $this->server['query_string'] ?? null;
        $this->server_port = $this->server['server_port'] ?? null;
        if (is_null($this->remote_addr)) {
            $this->remote_addr = $this->server['remote_addr'] ?? null;
        }
        return $this;
    }

    /**
     * Http请求的GET参数，相当于PHP中的$_GET，格式为数组。
     * @param $get
     * @return $this
     */
    private function withGet($get)
    {
        $this->get = $get;
        $_GET = $this->get;
        return $this;
    }

    /**
     * HTTP POST参数，格式为数组。
     * @param $post
     * @return $this
     */
    private function withPost($post)
    {
        $this->post = $post;
        $_POST = $this->post;
        return $this;
    }

    /**
     * HTTP请求携带的COOKIE信息，与PHP的$_COOKIE相同，格式为数组。
     * @param $cookie
     * @return $this
     */
    private function withCookie($cookie)
    {
        $this->cookie = $cookie;
        return $this;
    }

    /**
     * 获取原始的POST包体，用于非application/x-www-form-urlencoded格式的Http POST请求。
     * 等同于PHP的fopen('php://input')
     * @param $input
     * @return $this
     */
    private function withInput($input)
    {
        $this->input = $input;
        return $this;
    }

    /**
     * 获取请求方式
     * @return mixed
     */
    public function getRequestMethod()
    {
        return $this->request_method;
    }

    /**
     * @return mixed
     */
    public function getPathInfo()
    {
        return $this->path_info;
    }

    /**
     * @return mixed
     */
    public function getQueryString()
    {
        return $this->query_string;
    }

    /**
     * @return mixed
     */
    public function getRequestUri()
    {
        return $this->request_uri;
    }

    /**
     * 获取客户端IP
     * @return mixed
     */
    public function getRemoteAddr()
    {
        return $this->remote_addr;
    }

    /**
     * Http请求的GET参数，相当于PHP中的$_GET，格式为数组。
     * @param string $name
     * @return null
     */
    public function getParams($name = "")
    {
        if ($name == '') {
            return $this->get;
        }
        return $this->get[$name] ?? null;
    }

    /**
     * HTTP POST参数，格式为数组。
     * @param string $name
     * @return mixed|null
     */
    public function getPost($name = "")
    {
        if ($name == '') {
            return $this->post;
        }
        return $this->post[$name] ?? null;
    }

    /**
     * HTTP请求携带的COOKIE信息，与PHP的$_COOKIE相同，格式为数组。
     * @param string $name
     * @return mixed|null
     */
    public function getCookie($name = "")
    {
        if ($name == '') {
            return $this->cookie;
        }
        return $this->cookie[$name] ?? null;
    }

    /**
     * Http请求的头部信息。类型为数组，所有key均为小写。
     * @param string $name
     * @return mixed|null
     */
    public function getHeader($name = '')
    {
        if ($name == '') {
            return $this->header;
        }
        return $this->header[$name] ?? null;
    }

    /**
     * Http请求相关的服务器信息，相当于PHP的$_SERVER数组。
     * 包含了Http请求的方法，URL路径，客户端IP等信息。
     * @param string $name
     * @return mixed|null
     */
    public function getServer($name = '')
    {
        if ($name == '') {
            return $this->server;
        }
        return $this->server[$name] ?? null;
    }

    /**
     * 获取请求参数
     * @param string $name
     * @return array|mixed|null
     */
    public function getQuery($name = '')
    {
        if (empty($this->query_string)) {
            return null;
        }
        $params = [];
        $query = explode('&', $this->query_string);
        foreach ($query as $vo) {
            $arr = explode('=', $vo, 2);
            $params[$arr[0]] = $arr[1];
        }
        if (empty($name)) {
            return $params;
        } else {
            if (isset($params[$name]))
                return $params[$name];
            else
                return null;
        }
    }

    public function getData()
    {
        return $this->data ?? null;
    }

    public function __get($name)
    {
        if (isset($this->$name))
            return $this->$name;
        else
            return null;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
        return $this->$name;
    }

    /**
     * 请求解析器
     */
    public function explain()
    {
        $multiModule = Config::get('application.multi.module') ?? false;
        $defaultModule = Config::get('application.default.module') ?? 'Index';
        $defaultController = Config::get('application.default.controller') ?? 'Index';
        $defaultAction = Config::get('application.default.action') ?? 'Index';
        $pathInfo = substr($this->path_info, 1);
        if (empty($pathInfo)) {
            if ($multiModule) {
                $this->namespace = CONTROLLER_NAMESPACE . $defaultModule . '\\';
            } else {
                $this->namespace = CONTROLLER_NAMESPACE;
            }
            $this->controller = $defaultController;
            $this->action = $defaultAction;
        } else {
            if ($multiModule) {
                $pathInfo = explode('/', $pathInfo, 3);
                $this->namespace = CONTROLLER_NAMESPACE . (isset($pathInfo[0]) ? ucfirst($pathInfo[0]) : $defaultModule). '\\';
                $this->controller = isset($pathInfo[1]) ? ucfirst($pathInfo[1]) : $defaultController ;
                $this->action = $pathInfo[2] ?? $defaultAction;
            } else {
                $pathInfo = explode('/', $pathInfo, 2);
                $this->namespace = CONTROLLER_NAMESPACE;
                $this->controller = isset($pathInfo[0]) ? ucfirst($pathInfo[0]) : $defaultController;
                $this->action = $pathInfo[1] ?? $defaultAction;
            }
        }
        if ($this->request_method == 'POST') {
            $this->params = $this->post;
        } else if ($this->request_method == 'GET') {
            $this->params = $this->get;
        }
    }
}
