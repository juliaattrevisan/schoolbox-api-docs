<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Fs_Strategy_Enrolment extends Synchronise_External_Fs_Strategy_Abstract {

    public function __construct($csvFile, $columns) {
        parent::__construct($csvFile, $columns);
        $this->setup();
    }

    protected function setup() {
        // clear existing enrolments registered
        $this->clearEnrolment();
    }


    /**
     * Process the classes
     *
     */
    public function createIfExists() {
        $this->getVerboseFilter()->intro();

        if(is_array($this->entityData)) {

            $synStmt = Database::getInstance()->prepare("
                SELECT `id` FROM `user` WHERE synergy_id = :sid
            ");
            $classStmt = Database::getInstance()->prepare("
                SELECT `id` FROM `class` WHERE `code` = :code
            ");
            $enrolmentStmt = Database::getInstance()->prepare("
                INSERT INTO `class_user` VALUES (NULL, :classID, :userID)
            ");

            foreach($this->entityData as $enrolment) {
                // check the student and class exist
                $synStmt->execute(array('sid' => $enrolment['studentID']));
                $student = $synStmt->fetchColumn();
                $classStmt->execute(array('code' => $enrolment['classCode']));
                $class = $classStmt->fetchColumn();

                // got the teacher ID, insert class
                if($student && $class) {
                    $enrolmentStmt->execute(array(
                        'userID' => $student,
                        'classID' => $class
                    ));
                }
                elseif($this->getVerboseFilter()->getVerbose()) {
                    echo " Could not create enrolment for `{$enrolment['studentID']}` into `{$enrolment['classCode']}`"
                    ,": one or both don't exist\n";
                }
            }

        }
        else {
            throw new UnderflowException("No data parsed from the csv file.");
        }
    }

    protected function clearEnrolment() {
        Database::getInstance()->query("TRUNCATE class_user");
    }
}
