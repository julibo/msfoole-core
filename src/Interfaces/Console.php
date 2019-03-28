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

namespace Julibo\Msfoole\Interfaces;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface Console
{
    /**
     * 初始化工程
     */
    public function init();

    /**
     * 进程配置
     */
    public function configure();

    /**
     * 进程执行
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed
     */
    public function execute(InputInterface $input, OutputInterface $output);
}
