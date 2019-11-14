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

        static public function get($configName)
        {
            return ConfigLoader::getInstance()->get($configName);
        }

        public static function getTypeList() {
                self::getInstance();

                $query = "
                        SELECT DISTINCT `lu_config_type`.`type`
                        FROM `config`
                        INNER JOIN `lu_config_type`  ON `config`.`type_id`  = `lu_config_type`.`id`
                        WHERE 1=1 ";

                /* filter out references to leave module if not enabled */
                if (!self::get('boarder_leave_enabled')) {
                        $query .= "AND `config`.`type_id` != '".ConfigMapper::TYPE_BOARD."'";
                }

                $query .= "ORDER BY `config`.`configID` ASC";

                return DeprecatedDatabase::getInstance()->getAll($query);
        }
}
