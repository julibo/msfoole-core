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

    public function __construct($ip, $port, $identification, $permit, $token = '',  $ssl = false, $timeout = 1)
    {
        $this->client = new Client($ip, $port, $ssl);
        $this->client->setHeaders([
            'Host' => $this->host,
            'User-Agent' => 'Chrome/49.0.2587.3',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
            'token' => $token,
            'identification' => $identification,
            'permit' => $permit,
        ]);
        $this->client->set([ 'timeout' => $timeout]);
        $this->client->setDefer();

    }

    /**
     * @param string $url
     * @return mixed
     */
    public function getSubServer($url)
    {
        $this->client->get($url);
        $result  = $this->client->recv();
        return $result;
    }

    /**
     * @param $url
     * @param $data
     * @return mixed
     */
    public function postSubServer($url, $data)
    {
        $this->client->post($url, $data);
        $result = $this->client->recv();
        return $result;
    }

}
