<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole4的简易微服务API框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------

namespace Julibo\Msfoole;

use Swoole\Coroutine\Http\Client;

class HttpClient
{
    /**
     * @var Client
     */
    private $client;

    public function __construct($ip, $port, $permit = null, $identification = null, $token = null,  $ssl = false, $timeout = 10)
    {
        $this->client = new Client($ip, $port, $ssl);
        $this->client->setHeaders([
            'Host' => 'localhost',
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.77 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'accept-language' => 'zh-CN,zh;q=0.9',
            'permit' => $permit,
            'identification' => $identification,
            'token' => $token,
        ]);
        $this->client->set(['timeout' => $timeout]);
    }

    /**
     * @param $url
     * @return array
     */
    public function get($url) : array
    {
        $this->client->get($url);
        $data =  $this->client->body;
        $statusCode = $this->client->statusCode;
        $errCode = $this->client->errCode;
        $this->client->close();
        return ['data' => $data, 'statusCode' => $statusCode, 'errCode' => $errCode ];
    }

    /**
     * @param string $url
     * @return mixed
     */
    public function getDefer($url)
    {
        $this->client->setDefer();
        $this->client->get($url);
        return $this;
    }

    /**
     * @return mixed
     */
    public function recv()
    {
        $result  = $this->client->recv();
        return $result;
    }

    /**
     * @return array
     */
    public function answer() : array
    {
        $data =  $this->client->body;
        $statusCode = $this->client->statusCode;
        $errCode = $this->client->errCode;
        $this->client->close();
        return ['data' => $data, 'statusCode' => $statusCode, 'errCode' => $errCode ];
    }

    /**
     * @param $url
     * @param array $data
     * @return array
     */
    public function post($url, array $data)
    {
        $this->client->post($url, $data);
        $data =  $this->client->body;
        $statusCode = $this->client->statusCode;
        $errCode = $this->client->errCode;
        $this->client->close();
        return ['data' => $data, 'statusCode' => $statusCode, 'errCode' => $errCode];
    }

    /**
     * @param $url
     * @param array $data
     * @return $this
     */
    public function postDefer($url, array $data)
    {
        $this->client->setDefer();
        $this->client->post($url, $data);
        return $this;
    }
}
