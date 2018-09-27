<?php

use Schoolbox\Config\ConfigMapper;

require_once dirname(__FILE__) . '/../www/conf/conf.php';

/**
 * @deprecated use \Schoolbox\Config\Loader
 */
class Config extends DeprecatedModel {


        static private $instance = false;
        static private $data = false;
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
            return \Schoolbox\Config\ConfigMapper::getInstance()->get($configName);
        }

        static public function set($configName, $configValue) {
                return self::getInstance()->update(array('name' => $configName, 'value' => $configValue));
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

        public function getAvailableOptions() {
                self::getInstance();
                $query = "SELECT name FROM `config` WHERE `editable` = 'true'";
                $configlist = $this->db->getAll($query);
                $output = array();
                if (is_array($configlist)) {
                        foreach ($configlist as $config) {
                                $output[] = $config['name'];
                        }
                }
                return $output;
        }

        public static function getAllOfType($orderBy = '', $type = '') {
            $db = self::getInstance()->db;

            $query = "
                    SELECT *, `lu_config_field`.`field`, `lu_config_type`.`type`
                    FROM `config`
                    INNER JOIN `lu_config_field` ON `config`.`field_id` = `lu_config_field`.`id`
                    INNER JOIN `lu_config_type`  ON `config`.`type_id`  = `lu_config_type`.`id`
                    WHERE 1=1 ";

            if (!empty($type)) {
                $query .= "AND `lu_config_type`.`type` = '{$db->quote($type)}' ";
            }

            /* filter out references to leave module if not enabled */
            if (!self::get('boarder_leave_enabled')) {
                $query .= "AND `config`.`type_id` != '{$db->quote(ConfigMapper::TYPE_BOARD)}'";
            }

            if (!empty($orderBy)) {
                $query .= "ORDER BY {$db->quoteIdentifier($orderBy)}";
            }

            return $db->getAll($query);
        }

        public function updateArray($configList) {
                if (is_array($configList)) {
                        foreach ($configList as $configName => $configValue) {
                                $this->update(array('name' => $configName, 'value' => $configValue));
                        }
                }
        }

}
