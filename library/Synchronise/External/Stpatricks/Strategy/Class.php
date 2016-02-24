<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Stpatricks_Strategy_Class extends Synchronise_External_Stpatricks_Strategy_Abstract {

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

        /* Use this to combine classes in the db when they are listed
         * separately in the csv. They will do this to have multiple
         * teachers assigned to the same class, but to do that they
         * put the same class on multiple rows. Hence they are saved
         * as two separate instances in the db, but the second one
         * won't be found (it will only match the first one) since it
         * only searches by classCode, not its identifier
         */
        $processed = [];

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
                // got the teacher ID, insert class
                if($teacher) {

                    /* If it wasn't in our $processed list, insert it into
                     * the db and remember its identifier value
                     */
                    if (!isset($processed[$class['code']])) {
                        preg_match("#\([^)]*\) +(.*)#", $class['name'], $matches);

                        $classStmt->execute(array(
                            'name' => trim($matches[1]),
                            'code' => $class['code']
                        ));

                        $classID = $db->lastInsertId();

                        $processed[$class['code']] = $classID;
                    }
                    /* This class was previously inserted into the db, so
                     * prevent duplicates of the same class by reusing its
                     * identifier value so all teachers of this class get
                     * their timetable data
                     */
                    else {
                        $classID = $processed[$class['code']];
                    }

                    echo "adding staff member {$teacher} to class {$classID}\n";
                    $enrolmentStmt->execute(array(
                        'classID' => $classID,
                        'userID' => $teacher
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
