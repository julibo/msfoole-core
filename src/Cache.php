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

use think\helper\Arr;

class Cache
{

    /**
     * 缓存配置
     */
    protected $config = [];

    /**
     * 操作句柄
     */
    protected $handle;

    /**
     * 驱动
     * @var
     */
    protected $driver;

    /**
     * Cache constructor.
     * @param array $config
     */
//    public function __construct(array $config = [])
//    {
//        $this->config = $config;
//        $this->handle = $this->connect($config);
//    }

    /**
     * 对象初始化
     * @param array $config
     */
    public function init(array $config = [])
    {
        $this->config = $config;
        $this->handle = $this->connect($config);
    }

    /**
     * 连接缓存
     * @param array $options 配置数组
     * @return mixed
     */
    public function connect(array $options = [])
    {
        $type = !empty($options['driver']) ? $options['driver'] : 'Redis';
        $this->driver = $type;
        return Loader::factory($type, '\\Julibo\\Msfoole\\Cache\\Driver\\', $options);
    }

    /**
     * @return mixed
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->handle, $method], $args);
    }
}