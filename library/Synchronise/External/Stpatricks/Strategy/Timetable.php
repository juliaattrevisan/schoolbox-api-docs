<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Stpatricks_Strategy_Timetable extends Synchronise_External_Stpatricks_Strategy_Abstract {


    /**
     * Process the classes
     *
     */
    public function createIfExists() {
        $this->getVerboseFilter()->intro();

        if(is_array($this->entityData)) {

            // clear existing relations registered
            $this->clearTimetable();

            // prepare the bounds of the timetable from timetable_cycle
            // StPats is a single campus so we will assume one cycle definition
            // also no sync data is supplied, so retrieve existing
            $cycle = Database::getInstance()->query("SELECT * FROM `timetable_cycle`")->fetch(PDO::FETCH_ASSOC);

            // btw, also no sync data supplied for timetable_period


            $periodStmt = Database::getInstance()->prepare("
                SELECT `id` FROM `timetable_period` WHERE `day` = :day AND `sequence` = :seq
            ");
            $classStmt = Database::getInstance()->prepare("
                SELECT `id` FROM `class` WHERE `code` = :code
            ");
            $timetableStmt = Database::getInstance()->prepare("
                INSERT INTO `class_period` VALUES (NULL, :classID, :periodID)
            ");

            foreach($this->entityData as $timetable) {
                // check the period and class exist
                $periodStmt->execute(array('day' => $timetable['day'], 'seq' => $timetable['period']));
                $period = $periodStmt->fetchColumn();
                $classStmt->execute(array('code' => $timetable['classCode']));
                $class = $classStmt->fetchColumn();

                // got the period and class IDs, go ahead and insert the timetabling
                if($period && $class) {
                    $timetableStmt->execute(array(
                        'classID' => $class,
                        'periodID' => $period,
                    ));
                }
                elseif($this->getVerboseFilter()->getVerbose()) {
                    echo " Could not timetable `{$timetable['classCode']}` into day {$timetable['day']}, period ",
                    $timetable['period'], ": one or both of the class or period doesn't exist\n";
                }
            }

        }
        else {
            throw new UnderflowException("No data parsed from the csv file.");
        }
    }

    protected function clearTimetable() {
        Database::getInstance()->query("TRUNCATE class_period");
    }

}
