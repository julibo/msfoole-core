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

use think\Container;
use Julibo\Msfoole\Exception\ClassNotFoundException;

class Loader
{
    /**
     * 创建工厂对象实例
     * @access public
     * @param  string $name         工厂类名
     * @param  string $namespace    默认命名空间
     * @return mixed
     */
    public static function factory($name, $namespace = '', ...$args)
    {
        $class = false !== strpos($name, '\\') ? $name : $namespace . ucwords($name);
        if (class_exists($class)) {
            return Container::getInstance()->invokeClass($class, $args);
        } else {
            throw new ClassNotFoundException('class not exists:' . $class, $class);
        }
    }
}
