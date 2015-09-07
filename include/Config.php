<?php

require_once dirname(__FILE__) . '/../www/conf/conf.php';

/**
 * @deprecated use \Schoolbox\Config\Loader
 */
class Config extends DeprecatedModel {

    const FIELD_INPUT     = 1;
    const FIELD_TEXTAREA  = 2;
    const FIELD_BOOLEAN   = 3;
    const FIELD_SELECT    = 4;
    const FIELD_CHECKBOX  = 5;
    const FIELD_BYTES     = 6;
    const FIELD_SELECTKEY = 7;
    const FIELD_HTML      = 8;
    const FIELD_RESOURCE  = 9;
    const FIELD_SELECTLOOKUP = 10;
    const FIELD_COLOUR    = 11;
    const FIELD_FILE      = 12;

    const TYPE_SITE         = 1;
    const TYPE_SECURITY     = 2;
    const TYPE_PATHS        = 3;
    const TYPE_RESOURCES    = 4;
    const TYPE_POLICY       = 5;
    const TYPE_LOCALISATION = 6;
    const TYPE_EXTERNAL     = 7;
    const TYPE_EMAIL        = 8;
    const TYPE_COLOURS      = 9;
    const TYPE_3RD          = 10;
    const TYPE_MODULES      = 11;
    const TYPE_BOARD        = 12;
    const TYPE_WELCOME      = 13;
    const TYPE_DEBUGGING    = 14;


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

                $this->load();

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

        static private function load() {
                $configList = DeprecatedDatabase::getInstance()->getAll("SELECT * FROM `config`");
                self::inject($configList);
        }


        static public function inject($configList) {
                if (is_array($configList)) {
                        foreach ($configList as $config) {
                                if(isset(self::$data[$config['name']])) {
                                        //turn variable into an array if multiples
                                        if(!is_array(self::$data[$config['name']])) {
                                                $tempValue = self::$data[$config['name']];
                                                unset(self::$data[$config['name']]);
                                                self::$data[$config['name']][] = $tempValue;
                                        }
                                        self::$data[$config['name']][] = $config['value'];
                                }
                                else
                                        self::$data[$config['name']] = $config['value'];
                        }
                }
        }



        static public function get($configName) {
                self::getInstance();

                if (!self::$data) {
                        self::load();
                }

                if (isset(self::$data[$configName]))
                        return self::$data[$configName];
                elseif (isset(self::$consts[$configName]))
                        return self::$consts[$configName];
                elseif (defined($configName))
                        return constant($configName);
                else {
                        return '';
                }
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
                        $query .= "AND `config`.`type_id` != '".self::TYPE_BOARD."'";
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
                self::getInstance();

                $query = "
                        SELECT *, `lu_config_field`.`field`, `lu_config_type`.`type`
                        FROM `config`
                        INNER JOIN `lu_config_field` ON `config`.`field_id` = `lu_config_field`.`id`
                        INNER JOIN `lu_config_type`  ON `config`.`type_id`  = `lu_config_type`.`id`
                        WHERE 1=1 ";

                if (!empty($type)) {
                        $query .= "AND `lu_config_type`.`type` = '$type' ";
                }

                /* filter out references to leave module if not enabled */
                if (!self::get('boarder_leave_enabled')) {
                        $query .= "AND `config`.`type_id` != '".self::TYPE_BOARD."'";
                }

                if (!empty($orderBy)) {
                        $query .= "ORDER BY `$orderBy`";
                }

                return DeprecatedDatabase::getInstance()->getAll($query);
        }

        public function updateArray($configList) {
                if (is_array($configList)) {
                        foreach ($configList as $configName => $configValue) {
                                $this->update(array('name' => $configName, 'value' => $configValue));
                        }
                }
        }

}