<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Fs_Strategy_Class extends Synchronise_External_Fs_Strategy_Abstract {

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

            $enrolmentStmt = $db->prepare("
                INSERT INTO `class_user` (class_id, user_id) VALUES (:classID, :userID)
            ");

	    foreach($this->entityData as $class) {
                // check the staff member specified exists first
                $synStmt->execute(array('sid' => $class['staffID']));
                $teacher = $synStmt->fetchColumn();
                    $classStmt->execute(array(
                        'name' => $class['name'],
                        'code' => $class['code']
                    ));

                    $classID = $db->lastInsertId();
                    echo "adding staff member {$teacher} to class {$classID}\n";
                    $enrolmentStmt->execute(array(
                        'classID' => $classID,
                        'userID' => $teacher
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
