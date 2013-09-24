<?php

require_once dirname(__FILE__) . '/../www/conf/conf.php';

class Config extends DeprecatedModel {

        static private $instance = false;
        static private $data = false;
        static private $consts;

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
             /* Show non-editable field data as just plain text
			  * $query = "SELECT DISTINCT `type` FROM `".$this->_table."` WHERE `editable` = 'true' ORDER BY type";
			  */
			$query = "
				SELECT DISTINCT `lu_config_type`.`type`
				FROM `config`
				INNER JOIN `lu_config_type`  ON `config`.`type_id`  = `lu_config_type`.`id`
				ORDER BY `config`.`configID` ASC";

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
			/* Show non-editable field data as just plain text
			 * $query = "SELECT * FROM `".$this->_table."` WHERE `editable` = 'true' ";
			 */
			$query = "
				SELECT *, `lu_config_field`.`field`, `lu_config_type`.`type`
				FROM `config`
				INNER JOIN `lu_config_field` ON `config`.`field_id` = `lu_config_field`.`id`
				INNER JOIN `lu_config_type`  ON `config`.`type_id`  = `lu_config_type`.`id`
				WHERE 1=1 ";

			if (!empty($type)) {
				$query .= "AND `lu_config_type`.`type` = '$type' ";
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