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

// 加载基础文件
require __DIR__ . '/base.php';

$command = new Commands\Init('msfoole');
$application = new \Symfony\Component\Console\Application();
$application->add($command);
$application->setDefaultCommand('msfoole', true);
$application->run();