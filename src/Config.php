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

class Config
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    /**
     * 设置配置
     * @param $name 参数名
     * @param $value 值
     * @return array
     */
    public function __set($name, $value)
    {
        return $this->set($name, $value);
    }

    /**
     * 获取配置参数
     * @access public
     * @param  string $name 参数名
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * 检测是否存在参数
     * @access public
     * @param  string $name 参数名
     * @return bool
     */
    public function __isset($name)
    {
        return $this->has($name);
    }

    /**
     * 获取配置参数 为空则获取所有配置
     * @param null $name
     * @param null $default
     * @param bool $case
     * @return array|mixed|null
     */
    public function get($name = null, $default = null, $case = false)
    {
        $config = $this->config;
        // 无参数时获取所有
        if (empty($name)) {
            if (!$case) {
                $this->changeKeyCase($config);
            }
            return $config;
        }
        $name = explode('.', strtoupper($name));
        // 按.拆分成多维数组进行判断
        foreach ($name as $val) {
            if (isset($config[$val])) {
                $config = $config[$val];
            } else {
                return $default;
            }
        }
        if (!$case && is_array($config)) {
            $this->changeKeyCase($config);
        }
        return $config;
    }

    /**
     * 判断配置是否存在
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        return !is_null($this->get($name));
    }

    /**
     * 移除配置
     * @access public
     * @param  string  $name 配置参数名（支持三级配置 .号分割）
     * @return void
     */
    public function remove($name)
    {
        $name = explode('.', strtoupper($name), 3);
        if (count($name) == 3) {
            unset($this->config[$name[0]][$name[1]][$name[2]]);
        } else if (count($name) == 2) {
            unset($this->config[$name[0]][$name[1]]);
        } else {
            unset($this->config[$name[0]]);
        }
    }

    /**
     * 重置配置参数
     */
    public function reset()
    {
        $this->config = [];
    }

    /**
     * 解析配置文件
     * @param array|string $files
     * @param string $type
     * @return mixed
     */
    public function loadFile($files, $type = 'ini')
    {
        if (false !== strpos($type, '.')) {
            $type = str_replace('.', '', $type);
        }
        $type = ucwords($type);
        if (!is_array($files)) {
            $files = (array) $files;
        }
        foreach ($files as $file) {
            if (file_exists($file)) {
                $object = Loader::factory($type, '\\Julibo\\Msfoole\\Config\\Driver\\', $file);
                $this->set($object->parse());
            }
        }
    }

    /**
     * 加载配置文件夹配置项
     * @param $conf
     * @param string $type
     * @throws \Exception
     */
    public function loadConfig($conf, $type = '.ini')
    {
        // 配置文件解析
        if (!is_dir($conf)) {
            throw new \Exception("项目配置文件夹不存在");
        }
        $files = glob($conf . '*' . $type);
        if (empty($files)) {
            throw new \Exception("项目配置文件不存在");
        }
        $this->loadFile($files, $type);
    }

    /**
     * 设置配置参数 name为数组则为批量设置
     * @param $name
     * @param $value
     * @return array
     */
    public function set($name, $value = null)
    {
        if (empty($name)) {
            return $this->config;
        }
        $list = [];
        if (is_string($name)) {
            $list = [$name=>$value];
        } else if (is_array($name)) {
            $list = $name;
        }
        foreach ($list as $key => $v) {
            if (false !== strpos($key, '.')) {
                $split = '.';
            } else {
                $split = '__';
            }
            $key = explode($split, strtoupper($key), 3);
            // 优先读取系统环境变量
            $value = getenv(implode('__', $key)) ?: $v;
            if (false !== strpos($value, ',')) {
                $value = explode(',', $value);
            }
            if (count($key) == 3) {
                $this->config[$key[0]][$key[1]][$key[2]] = $value;
            } else if (count($key) == 2) {
                $this->config[$key[0]][$key[1]] = $value;
            } else {
                $this->config[$key[0]] = $value;
            }
        }
        return $this->config;
    }

    /**
     * 将数组键名改写为小写格式
     * @param $config
     */
    private function changeKeyCase(&$config)
    {
        $config = array_change_key_case($config);
        foreach ($config as $key => $value) {
            if ( is_array($value) ) {
                $this->changeKeyCase($config[$key]);
            }
        }
    }
}
