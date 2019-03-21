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

use Redis;

class RedisDriver
{
    /**
     * @var array
     */
    private static $instance = [];

    /**
     * @var int
     */
    private $retryTimes = 5;

    /**
     * @var Redis
     */
    private $redis;

    /**
     * 默认配置
     * @var array
     */
    private $config = [
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'db'     => 0,
        'timeout'    => 0,
        'expire'     => 0,
        'prefix'     => '',
        'serialize'  => true,
    ];

    /**
     * 构造方法
     * RedisDriver constructor.
     * @param array $config
     */
    private function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->redis = new Redis();
        $this->connect($this->config);
    }

    /**
     * 实例化
     * @param array $config
     * @return RedisDriver
     */
    public static function instance(array $config = []) : self
    {
        $drive = md5(serialize($config));
        if (empty(self::$instance[$drive])) {
            self::$instance[$drive] = new self($config);
        }
        return self::$instance[$drive];
    }

    /**
     * 建立连接
     * @param array $config
     */
    private function connect(array $config)
    {
        $host     = $config['host'];
        $port     = $config['port'];
        $password = $config['password'];
        $database = $config['db'];
        $timeout = $config['timeout'];
        $this->redis->connect($host, $port, $timeout);
        if (!empty($password)) {
            $this->redis->auth($password);
        }
        $this->redis->select($database);
    }

    /**
     * @return bool
     */
    public function ping() : bool
    {
        if ($this->redis->PING() == '+PONG')
            return true;
        else
            return false;
    }

    /**
     * @return array
     */
    public function getConfig() : array
    {
        return $this->config;
    }

    /**
     * 遍历redis键值对
     * @param string $pattern
     * @param int $count
     * @param null $iterator
     * @return array|bool
     */
    public function scan(string $pattern = '', int $count = 1000, $iterator = null)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                $result = $this->redis->SCAN($iterator, $pattern, $count);
                return ['iterator'=>$iterator, 'result'=>$result];
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }

    /**
     * 清除所有缓存
     * @return bool
     */
    public function clear() :bool
    {
        $iterator = null;
        $pattern = $this->config['prefix'] .  '*';
        do {
            $list = $this->scan($pattern, 1000, $iterator);
            $iterator = $list['iterator'];
            if (!empty($list['result'])) {
                foreach ($list['result'] as $li) {
                    $this->redis->del($li);
                }
            }
        } while ($iterator != 0);
        return true;
    }

    /**
     * @param $key
     * @return bool|string
     */
    public function get(string $key)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                return $this->redis->GET($key);
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }

    /**
     * @param $key
     * @param $val
     * @param null $expire
     * @return bool
     */
    public function set(string $key, $val, $expire = null)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                if (is_null($expire)) {
                    $expire = $this->config['expire'];
                }
                if ($expire) {
                    return $this->redis->setex($key, $expire, $val);
                } else {
                    return $this->redis->set($key, $val);
                }
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }

    /**
     * @param $key
     * @return bool|string
     */
    public function del(string $key)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                return $this->redis->DEL($key);
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }

    /**
     * @param $key
     * @param int $step
     * @return bool|int
     */
    public function incrby(string $key, int $step = 1)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                return $this->redis->incrby($key, $step);
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }

    /**
     * @param $key
     * @param int $step
     * @return bool|int
     */
    public function decrby(string $key, int $step = 1)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                return $this->redis->decrby($key, $step);
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }

    /**
     *
     * @param $name
     * @param $arguments
     * @return bool
     */
    public function __call($name, $arguments)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                $result = $this->redis->$name(...$arguments);
                return $result;
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }
}
