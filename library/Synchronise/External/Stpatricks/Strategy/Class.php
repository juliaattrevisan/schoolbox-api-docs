<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Stpatricks_Strategy_Class extends Synchronise_External_Stpatricks_Strategy_Abstract {

    /**
     * Process the classes
     *
     */
    public function createIfExists() {
        $this->getVerboseFilter()->intro();

        if(is_array($this->entityData)) {

            // clear existing relations registered
            $this->clearClasses();

            $synStmt = Database::getInstance()->prepare("
                SELECT `id` FROM `user` WHERE synergy_id = :sid
            ");
            $classStmt = Database::getInstance()->prepare("
                INSERT INTO `class` VALUES (NULL, :name, :code, '')
            ");

            foreach($this->entityData as $class) {
                // check the staff member specified exists first
                $synStmt->execute(array('sid' => $class['staffID']));
                $teacher = $synStmt->fetchColumn();
                // got the teacher ID, insert class
                if($teacher) {
                    preg_match("#\([^)]*\) +(.*)#", $class['name'], $matches);
                    $classStmt->execute(array(
                        'name' => trim($matches[1]),
                        'code' => $class['code']
                    ));
                }
                elseif($this->getVerboseFilter()->getVerbose()) {
                    echo " Could not create class `{$class['name']}`, teacher with synergy ID `{$class['staffID']}` doesn't exist\n";
                }
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
