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

namespace Julibo\Msfoole\Exception;

class ClassNotFoundException extends \RuntimeException
{
    protected $class;

    public function __construct($message, $class = '')
    {
        $this->message = $message;
        $this->class   = $class;
        $this->code = 999;
    }

    /**
     * 获取类名
     * @access public
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }
}
