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

use Swoole\Coroutine as co;

class Channel
{
    private static $instance = [];

    private $chan;

    private function __construct($capacity)
    {
        $this->chan = new co\Channel($capacity);
    }

    public static function instance($capacity = 100, $name = 'default')
    {
        if (!isset(self::$instance[$name])) {
            self::$instance[$name] = new static($capacity);
        }
        return self::$instance[$name];
    }

    public function push($data)
    {
        return $this->chan->push($data);
    }

    public function pop()
    {
        return $this->chan->pop();
    }

    public function stats()
    {
        return $this->chan->stats();
    }

    public function close()
    {
        $this->chan->close();
    }

    public function length()
    {
        return $this->chan->length();
    }

    public function isEmpty()
    {
        return $this->chan->isEmpty();
    }

    public function isFull()
    {
        return $this->chan->isFull();
    }

    public function getErrCode()
    {
        return $this->chan->errCode;
    }

    public function getCap()
    {
        return $this->chan->capacity;
    }
}
