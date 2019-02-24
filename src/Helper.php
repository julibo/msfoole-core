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

use Julibo\Msfoole\Facade\Config;

class Helper
{
    const WEBHOOK = 'https://oapi.dingtalk.com/robot/send?access_token=';

    public static function request_by_curl($remote_server, $post_string) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $remote_server);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array ('Content-Type: application/json;charset=utf-8'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 线下环境不用开启curl证书验证, 未调通情况可尝试添加该代码
        // curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        // curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    /**
     * 发送钉钉
     * @param string $message
     * @return bool
     */
    public static function sendDingRobotTxt($message)
    {
        $result = false;
        if (Config::get('extra.dd.hook')) {
            $data = array ('msgtype' => 'text','text' => array ('content' => $message));
            $data_string = json_encode($data);
            $apiData = self::request_by_curl(self::WEBHOOK . Config::get('extra.dd.hook'), $data_string);
            $apiData = json_decode($apiData);
            if (!empty($apiData) && isset($apiData->errcode) && $apiData->errcode == 0) {
                $result = true;
            }
        }
        return $result;
    }

    /**
     * 生成UUID
     * @return mixed|string
     */
    public static function  guid()
    {
        if (function_exists('com_create_guid')) {
            $uuid = com_create_guid();
        } else {
            mt_srand((double)microtime()*10000);
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);
            $uuid   = chr(123)
                .substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12)
                .chr(125);
        }
        $uuid = str_replace(array('-', '{', '}'), '', $uuid);
        return $uuid;
    }

    /**
     * 判断字符串是否为 Json 格式
     *
     * @param  string     $data  Json 字符串
     * @param  bool       $assoc 是否返回关联数组。默认返回对象
     *
     * @return bool|array 成功返回转换后的对象或数组，失败返回 false
     */
    public static function isJson($data = '', $assoc = false) {
        $data = json_decode($data, $assoc);
        if ($data && (is_object($data)) || (is_array($data) && !empty(current($data)))) {
            return $data;
        }
        return false;
    }

    /**
     * 设置进程名称
     * @param $title
     */
    public static function setProcessTitle($title)
    {
        if (!IS_DARWIN && function_exists('swoole_set_process_name')) {
            swoole_set_process_name($title);
        }
    }

}
