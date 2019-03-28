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

use Julibo\Msfoole\Exception;

class ServerException extends Exception
{
    /**
     * ServerException constructor.
     * @param $message
     * @param int $code
     * @param string $file
     * @param string $line
     */
    public function __construct($message, $code = 899, $file = null, $line = null)
    {
        $this->message  = $message;
        $this->code     = $code;
        if ($file) {
            $this->file     = $file;
        }
        if ($line) {
            $this->line     = $line;
        }
    }
}