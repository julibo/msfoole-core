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

use think\Log as ThinkLog;
use Swoole\Exception;

class Log extends ThinkLog
{
    /**
     * @var
     */
    private $chan;

    /**
     * 请求方式
     * @var
     */
    private $method;

    /**
     * 请求URI
     */
    private $uri;

    /**
     * 访问IP
     */
    private $ip;

    /**
     * 日志初始化
     * @param array $config
     * @param $chan
     * @return $this
     * @throws \Exception
     */
    public function launch($config = [], $chan = null)
    {
        $this->init($config);
        $this->chan = $chan;
        return $this;
    }

    /**
     * 开启通道
     * @param $chan
     */
    public function setChan($chan)
    {
        $this->chan = $chan;
    }

    /**
     * 设置环境参数
     * @param $key
     * @param null $method
     * @param null $uri
     * @param null $ip
     */
    public function setEnv($key = null, $method = null, $uri = null, $ip = null)
    {
        $this->key = $key ?? Helper::guid();
        $this->method = $method;
        $this->uri = $uri;
        $this->ip = $ip;
    }

    /**
     * 向队列中推送日志
     * @param $msg
     * @param string $type
     * @param array $context
     */
    public function pushRecord($msg, $type = 'info', array $context = [])
    {
        $data = ['type' => 0];
        $msg = "{$this->ip} {$this->method} {$this->uri} " . $msg;
        $data['log'] = [
            'msg' => $msg,
            'type' => $type,
            'context' => $context,
            'key' => $this->key
        ];
        $this->chan->push($data);
    }

    /**
     * 将数据写入日志
     * @param array $data
     */
    public function saveData(array $data)
    {
        $key = $data['key'] ?? null;
        if ($key) {
            $this->key($key);
        }
        $msg = sprintf('[ %s ]  %s', $key, $data['msg']);
        $type = $data['type'] ?? 'info';
        $context = $data['context'] ?? [];
        $this->record($msg, $type, $context);
    }

    /**
     * 记录日志信息
     * @access public
     * @param  string $level     日志级别
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @param bool $force 强制直接写入
     * @return void
     */
    public function log($level, $message, array $context = [], $force = false)
    {
        if ($this->chan && $force == false) {
            $this->pushRecord($message, $level, $context);
        } else {
            $msg = "{$this->ip} {$this->method} {$this->uri} " . $message;
            $data = [
                'msg' => $msg,
                'type' => $level,
                'context' => $context,
                'key' => $this->key
            ];
            $this->saveData($data);
        }
    }

    /**
     * 记录emergency信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录警报信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function alert($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录紧急情况
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function critical($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录错误信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @param bool $force 强制直接写入
     * @return void
     */
    public function error($message, array $context = [], $force = false)
    {
        $this->log(__FUNCTION__, $message, $context, $force);
    }

    /**
     * 记录warning信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function warning($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录notice信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function notice($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录一般信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function info($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录调试信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function debug($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录sql信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function sql($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    /**
     * 记录慢查询信息
     * @access public
     * @param  mixed  $message   日志信息
     * @param  array  $context   替换内容
     * @return void
     */
    public function slow($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }
}
