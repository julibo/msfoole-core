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
    private $client;

    private $host = 'localhost';

    public function __construct($ip, $port, $permit = null, $identification = null, $token = null,  $ssl = false, $timeout = 1)
    {
        $this->client = new Client($ip, $port, $ssl);
        $this->client->setHeaders([
            'Host' => $this->host,
            'User-Agent' => 'Chrome/49.0.2587.3',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
            'permit' => $permit,
            'identification' => $identification,
            'token' => $token,
        ]);
        $this->client->set([ 'timeout' => $timeout]);
    }

    /**
     * @param $url
     * @return array
     */
    public function get($url)
    {
        $this->client->get($url);
        $data =  $this->client->body;
        $statusCode = $this->client->statusCode;
        $this->client->close();
        return ['data' => $data, 'statusCode' => $statusCode ];
    }

    /**
     * @param string $url
     * @return mixed
     */
    public function getDefer($url)
    {
        $this->client->setDefer();
        $this->client->get($url);
        return $this->client;
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
     * @param $url
     * @param array $data
     * @return array
     */
    public function post($url, array $data)
    {
        $this->client->post($url, $data);
        $data =  $this->client->body;
        $statusCode = $this->client->statusCode;
        $this->client->close();
        return ['data' => $data, 'statusCode' => $statusCode];
    }

    /**
     * @param $url
     * @param $data
     */
    public function postDefer($url, array $data)
    {
        $this->client->setDefer();
        $this->client->post($url, $data);
    }
}
