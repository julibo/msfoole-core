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

namespace Julibo\Msfoole\Cache;

/**
 * 缓存基础类
 * Class Driver
 * @package Julibo\Msfoole\Cache
 */
abstract class Driver
{
    /**
     * 驱动句柄
     * @var null
     */
    protected $handler = null;

    /**
     * 缓存参数
     * @var array
     */
    protected $options = [];

    /**
     * 判断缓存是否存在
     * @param $name 缓存变量名
     * @return bool
     */
    abstract public function has($name);

    /**
     * 读取缓存
     * @param $name 缓存变量名
     * @param null $default 默认值
     * @return mixed
     */
    abstract public function get($name, $default = null);

    /**
     * 写入缓存
     * @param $name 缓存变量名
     * @param $value 存储数据
     * @param null $expire 有效期， 0表示永久
     * @return mixed
     */
    abstract public function set($name, $value, $expire = null);

    /**
     * 自增缓存 （针对数值缓存）
     * @param $name 缓存变量名
     * @param int $step 步长
     * @return mixed
     */
    abstract public function inc($name, $step = 1);

    /**
     * 自减缓存
     * @param $name 缓存变量名
     * @param int $step 步长
     * @return mixed
     */
    abstract public function dec($name, $step = 1);

    /**
     * 删除缓存
     * @param $name
     * @return mixed
     */
    abstract public function del($name);

    /**
     * 清空缓存
     * @return mixed
     */
    abstract public function clear();

    /**
     * 获取缓存剩余有效期
     * @param $name
     * @return mixed
     */
    abstract public function getPeriod($name);

    /**
     * 获取实际缓存标识
     * @param $name
     * @return string
     */
    protected function getCacheKey($name)
    {
        return $this->options['prefix'] . $name;
    }

    /**
     * 获取缓存并删除
     * @param $name
     * @return mixed|void
     */
    public function pull($name)
    {
        $result = $this->get($name, null);
        if (!is_null($result)) {
            $this->del($name);
            return $result;
        } else {
            return null;
        }
    }

    /**
     * 返回句柄对象
     * @return null
     */
    public function handler()
    {
        return $this->handler;
    }
}
