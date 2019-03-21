<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole4的简易微API服务框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------

// 环境常量
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('ROOT_PATH') or define('ROOT_PATH', realpath(dirname($_SERVER['SCRIPT_FILENAME'])) . DS);
defined('APP_PATH') or define('APP_PATH', ROOT_PATH . 'app' . DS);
defined('RUN_PATH') or define('RUN_PATH', ROOT_PATH . 'runtime' . DS);
defined('TEMP_PATH') or define('TEMP_PATH', RUN_PATH . 'temp' . DS);
defined('LOG_PATH') or define('LOG_PATH', RUN_PATH . 'logs' . DS);
defined('CONF_PATH') or define('CONF_PATH', ROOT_PATH . 'config' . DS);
defined('CONF_EXT') or define('CONF_EXT', '.ini');
defined('ENV_EXT') or define('ENV_EXT', '.yml');
defined('CONTROLLER_NAMESPACE') or define('CONTROLLER_NAMESPACE', '\\App\\Controller\\');
defined('SERVER_PID') or define('SERVER_PID', TEMP_PATH . 'msfoole.pid');
define('IS_CLI', PHP_SAPI == 'cli' ? true : false);
define('IS_DARWIN', strpos(PHP_OS, 'Darwin') !== false);

if (!IS_CLI) {
    exit('仅限命令行模式下运行' . PHP_EOL);
}

// 自动加载
require ROOT_PATH . 'vendor/autoload.php';

// 加载项目默认配置
\Julibo\Msfoole\Facade\Config::loadFile(__DIR__ . '/project.yml', ENV_EXT);
// 配置文件解析
\Julibo\Msfoole\Facade\Config::loadConfig(CONF_PATH, CONF_EXT);

// 初始化日志
$logConf = \Julibo\Msfoole\Facade\Config::get('log');
\Julibo\Msfoole\Facade\Log::launch($logConf);

// 注册错误和异常处理机制
\Julibo\Msfoole\Error::register();
