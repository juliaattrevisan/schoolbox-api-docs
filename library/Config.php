<?php

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\Loader as ConfigLoader;

require_once dirname(__FILE__) . '/../www/conf/conf.php';

/**
 * @deprecated use \Schoolbox\Config\Loader
 */
class Config extends DeprecatedModel {


        static private $instance = false;
        static private $consts;

        /**
         * @deprecated use \Schoolbox\Config\Loader
         */
        public function __construct () {

                $this->_table = 'config';
                $this->_index = 'name';

                parent::__construct();
        }

        static public function getInstance() {
                if (!self::$instance) {
                    $w = realpath( dirname(__FILE__) . "/..");
                    self::$consts = array(
                        'siteRoot' => $w,
                        'webRoot'  => "$w/www",
                        'webBase'  => ''
                    );
                    self::$instance = new self();
                }
                return self::$instance;
        }

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
