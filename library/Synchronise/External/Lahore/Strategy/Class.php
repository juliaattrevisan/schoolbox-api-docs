<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Lahore_Strategy_Class extends Synchronise_External_Lahore_Strategy_Abstract {

    public function __construct($csvFile, $columns) {
        parent::__construct($csvFile, $columns);
        $this->setup();
    }

    protected function setup() {
        // clear existing classes registered
        $this->clearClasses();
    }


    /**
     * Process the classes
     *
     */
    public function createIfExists() {
        $this->getVerboseFilter()->intro();

        if(is_array($this->entityData)) {

            $db = Database::getInstance();

            $synStmt = $db->prepare("
                SELECT `id` FROM `user` WHERE synergy_id = :sid
            ");
            $classStmt = $db->prepare("
                INSERT INTO `class` VALUES (NULL, :name, :code, '')
            ");

            foreach($this->entityData as $class) {
                    $classStmt->execute(array(
                        'name' => $class['name'],
                        'code' => $class['code']
                    ));
            }

        }
        else {
            throw new UnderflowException("No data parsed from the csv file.");
        }
    }

    protected function clearClasses() {
        Database::getInstance()->query("TRUNCATE class");
    }

}
