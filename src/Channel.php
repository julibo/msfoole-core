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

use Swoole\Coroutine as co;

class Channel
{
    /**
     * @var array
     */
    private static $instance = [];

    /**
     * @var co\Channel
     */
    private $chan;

    /**
     * 构造方法
     * Channel constructor.
     * @param $capacity
     */
    private function __construct(int $capacity)
    {
        $this->chan = new co\Channel($capacity);
    }

    /**
     * 初始化
     * @param int $capacity
     * @param string $name
     * @return mixed
     */
    public static function instance(int $capacity = 100, string $name = 'default') : self
    {
        if (!isset(self::$instance[$name])) {
            self::$instance[$name] = new self($capacity);
        }
        return self::$instance[$name];
    }

    /**
     * 向通道中写入数据
     * @param mixed $data
     * @param float $timeout
     * @return bool
     */
    public function push($data, float $timeout = -1) : bool
    {
        return $this->chan->push($data, $timeout);
    }

    /**
     * 从通道中读取数据。
     * @return mixed
     */
    public function pop()
    {
        return $this->chan->pop();
    }

    /**
     * 获取通道的状态
     * @return array
     */
    public function stats() : array
    {
        return $this->chan->stats();
    }

    /**
     * 关闭通道。并唤醒所有等待读写的协程
     * 唤醒所有生产者协程，push方法返回false
     * 唤醒所有消费者协程，pop方法返回false
     */
    public function close()
    {
        $this->chan->close();
    }

    /**
     * 获取通道中的元素数量
     * @return int
     */
    public function length() : int
    {
        return $this->chan->length();
    }

    /**
     * 判断当前通道是否为空
     * @return bool
     */
    public function isEmpty() : bool
    {
        return $this->chan->isEmpty();
    }

    /**
     * 判断当前通道是否已满
     * @return bool
     */
    public function isFull() : bool
    {
        return $this->chan->isFull();
    }

    /**
     * 错误码
     * 默认成功 0
     * 超时 pop失败时(超时)会置为-1
     * channel已关闭,继续操作channel，设置错误码 -2
     * @return int
     */
    public function getErrCode() : int
    {
        return $this->chan->errCode;
    }

    /**
     * 通道容量
     * @return int
     */
    public function getCap() : int
    {
        return $this->chan->capacity;
    }
}
