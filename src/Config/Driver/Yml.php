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

namespace Julibo\Msfoole\Config\Driver;

use Symfony\Component\Yaml\Yaml as SymfonyYaml;

class Yml
{
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function parse()
    {
        $result = [];
        $yamlConfig = SymfonyYaml::parse(file_get_contents($this->config));
        if (!empty($yamlConfig)) {
            if (isset($yamlConfig['environments'])) {
                $result = $yamlConfig['environments'];
            } else {
                $result = $yamlConfig;
            }
        }
        return $result;
    }
}
