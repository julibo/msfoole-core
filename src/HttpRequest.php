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

use Swoole\Http\Request as SwooleRequest;
use Julibo\Msfoole\Facade\Config;

class HttpRequest
{

    /**
     * @var
     */
    private $serviceName;

    /**
     * 过滤器
     * @var mixed
     */
    private $filter;

    /**
     * http请求原始报文
     */
    private $data;

    /**
     * Http请求的头部信息，格式为数组。
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
     * @var
     */
    private $host;

    /**
     * @var
     */
    private $origin;

    /**
     * @var
     */
    private $request_method;

    /**
     * @var
     */
    private $request_uri;

    /**
     * @var
     */
    private $path_info;

    /**
     * @var
     */
    private $query_string;

    /**
     * @var
     */
    private $remote_addr;

    /**
     * @var 
     */
    private $files;

    /**
     * 请求唯一识别码
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
     * @var　模块
     */
    public $modular;

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
     * @var
     */
    private $request;

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
        $this->request = $request;
    }


    public function init()
    {
        $this->withData($this->request->getData())
            ->withHeader($this->request->header)
            ->withServer($this->request->server);
        if (!empty($this->request->get))
            $this->withGet($this->request->get);
        if (!empty($this->request->post))
            $this->withPost($this->request->post);
        if (!empty($this->request->cookie))
            $this->withCookie($this->request->cookie);
        $rawContent = $this->request->rawContent();
        if (!empty($rawContent)) {
            $this->withInput($rawContent);
        }
        if (!empty($this->request->files)) {
            $this->withFile($this->request->files);
        }
        // $this->explain();
    }

    /**
     * 获取完整原始报文
     * @param string $data
     * @return $this
     */
    private function withData(string $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Http请求的头部信息。类型为数组，所有key均为小写。
     * @param array $header
     * @return $this
     */
    private function withHeader(array $header)
    {
        $this->header = $header;
        $this->host = $this->header['host'] ?? null;
        $this->origin = $this->header['origin'] ?? null;
        $this->identification = $this->header['identification'] ?? Helper::guid();
        if (isset($this->header['x-real-ip'])) {
            $this->remote_addr = $this->header['x-real-ip'];
        }
        return $this;
    }

    /**
     * Http请求相关的服务器信息，相当于PHP的$_SERVER数组。
     * 包含了Http请求的方法，URL路径，客户端IP等信息。
     * @param array $server
     * @return $this
     */
    private function withServer(array $server)
    {
        $this->server = $server;
        $this->request_method = $this->server['request_method'] ?? null;
        $this->request_uri = $this->server['request_uri'] ?? null;
        $this->path_info = $this->server['path_info'] ?? null;
        $this->query_string = $this->server['query_string'] ?? null;
        if (is_null($this->remote_addr)) {
            $this->remote_addr = $this->server['remote_addr'] ?? null;
        }
        return $this;
    }

    /**
     * Http请求的GET参数，相当于PHP中的$_GET，格式为数组。
     * @param array $get
     * @return $this
     */
    private function withGet(array $get)
    {
        $this->get = $get;
        $_GET = $this->get;
        return $this;
    }

    /**
     * HTTP POST参数，格式为数组。
     * @param array $post
     * @return $this
     */
    private function withPost(array $post)
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
    private function withCookie(array $cookie)
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
    private function withInput(string $input)
    {
        $this->input = $input;
        return $this;
    }

    /**
     * 文件上传信息
     * @param array $files
     */
    private function withFile(array $files)
    {
        $this->files = $files;
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
                $this->modular = $pathInfo[0];
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

    /**
     * 服务解构
     */
    public function resolve()
    {
        $pathInfo = substr($this->path_info, 1);
        if (strpos($pathInfo, '/') === false) {
            $this->serviceName = ucfirst($pathInfo);
            $this->path_info = '/';
        } else {
            $serviceName = Helper::cut_str($pathInfo, '/', 0);
            $this->serviceName = ucfirst($serviceName);
            $this->path_info = '/' . substr($pathInfo,strpos($pathInfo,'/') + 1);
        }
        $this->explain();
    }

}
