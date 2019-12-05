<?php

use Schoolbox\Config\Loader as ConfigLoader;

/**
 * @deprecated use \Schoolbox\Config\Loader
 */
class Config
{
    /**
     * Wrapper around `\Schoolbox\Config\Loader::get`
     *
     * @deprecated this should not be used: please inject the config options you
     *             need into wherever they are needed instead of calling this
     *             and instantiating a DB connection from unknown places
     *
     * @param $configName
     * @return mixed
     */
    public static function get($configName)
    {
        return ConfigLoader::getInstance()->get($configName);
    }
}
