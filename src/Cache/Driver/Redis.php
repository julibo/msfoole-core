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

namespace Julibo\Msfoole\Cache\Driver;

use Julibo\Msfoole\RedisDriver;
use Julibo\Msfoole\Cache\Driver;
use Julibo\Msfoole\Helper;

class Redis extends Driver
{
    public function __construct(array $options = [])
    {
        $this->handler = RedisDriver::instance($options);
        $this->options = $this->handler->getConfig();
    }

    public function has($name)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->EXISTS($key);
    }

    public function getPeriod($name)
    {
        $key = $this->getCacheKey($name);
        $deadline = $this->handler->TTL($key);
        return $deadline;
    }

    public function get($name, $default = null)
    {
        if (!$this->has($name)) {
            $value = $default;
        } else {
            $key = $this->getCacheKey($name);
            $value = $this->handler->get($key);
            $arrValue = Helper::isJson($value, true);
            if ($this->options['serialize'] && $arrValue) {
                $value = $arrValue;
            }
        }
        return $value;
    }

    public function set($name, $value, $expire = null)
    {
        if (!is_scalar($value) && $this->options['serialize']) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $key = $this->getCacheKey($name);
        return $this->handler->set($key, $value, $expire);
    }

    public function clear()
    {
        return $this->handler->clear();
    }

    public function del($name)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->del($key);
    }

    public function inc($name, $step = 1)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->incrby($key, $step);
    }

    public function dec($name, $step = 1)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->decrby($key, $step);
    }

    public function __call($name, $arguments)
    {
        return $this->handler->$name(...$arguments);
    }
}
