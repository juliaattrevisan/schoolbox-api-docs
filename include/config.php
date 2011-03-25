<?php

require_once dirname(__FILE__) . '/../www/conf/conf.php';
require_once SITE_root . '/include/model.php';

class Config extends Model {

        static private $instance = false;
        static private $data = false;

        public function __construct () {

                $this->_table = 'config';
                $this->_index = 'name';

                parent::__construct();

                $this->load();

        }

        static public function getInstance() {
                if (!self::$instance) {
                        self::$instance = new self();
                }
                return self::$instance;
        }

        static private function load() {
                $configList = dbManager::getInstance()->getAll("SELECT * FROM `config`");
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
                elseif (defined($configName))
                        return constant($configName);
                else {
                        return '';
                }
        }

        static public function set($configName, $value) {
                self::getInstance();

                self::update(array('name' => $configName, 'value' => $configValue));
        }

        public function getTypeList() {
                $query = "SELECT DISTINCT `type` FROM `".$this->_table."` WHERE `editable` = 'true' ORDER BY type";
                return $this->db->getAll($query);
        }

        public function getAvailableOptions() {
                $query = "SELECT name FROM `".$this->_table."` WHERE `editable` = 'true'";
                $configlist = $this->db->getAll($query);
                $output = array();
                if (is_array($configlist)) {
                        foreach ($configlist as $config) {
                                $output[] = $config['name'];
                        }
                }
                return $output;
        }

        public function getAll($orderBy = '', $type = '') {
                $query = "SELECT * FROM `".$this->_table."` WHERE `editable` = 'true' ";
                if (!empty($type))
                        $query .= "AND `type` = '$type' ";
                if (!empty($orderBy))
                        $query .= "ORDER BY `$orderBy`";

                return $this->db->getAll($query);
        }

        public function updateArray($configList) {
                if (is_array($configList)) {
                        foreach ($configList as $configName => $configValue) {
                                $this->update(array('name' => $configName, 'value' => $configValue));
                        }
                }
        }

}

/* name shortening */
class Cfg extends Config { }

?>