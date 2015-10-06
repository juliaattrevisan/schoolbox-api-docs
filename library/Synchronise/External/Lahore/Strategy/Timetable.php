<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Lahore_Strategy_Timetable extends Synchronise_External_Lahore_Strategy_Abstract {

    public function __construct($csvFile, $columns) {
        parent::__construct($csvFile, $columns);
        $this->setup();
    }

    protected function setup() {
        // clear existing timetable registered
        $this->clearTimetable();
    }


    /**
     * Process the classes
     *
     */
    public function createIfExists() {
        $this->getVerboseFilter()->intro();

        if(is_array($this->entityData)) {

            // prepare the bounds of the timetable from timetable_cycle
            // StPats is a single campus so we will assume one cycle definition
            // also no sync data is supplied, so retrieve existing
            $cycle = Database::getInstance()->query("SELECT * FROM `timetable_cycle`")->fetch(PDO::FETCH_ASSOC);

            // btw, also no sync data supplied for timetable_period

            // if it's days 1 - 4, use the day 0 template, and on day 5 use day 5
            $periodStmt = Database::getInstance()->prepare("
                SELECT `id` FROM `timetable_period` WHERE `day` = :day AND `title` LIKE :seq
            ");
            $classStmt = Database::getInstance()->prepare("
                SELECT `id` FROM `class` WHERE `code` = :code
            ");
            $timetableStmt = Database::getInstance()->prepare("
                INSERT INTO `class_period` (id, class_id, period_id, room) VALUES (NULL, :classID, :periodID, :room)
            ");

            foreach($this->entityData as $timetable) {
                // check the period and class exist
                $periodStmt->execute(array('day' => trim($timetable['day']), 'seq' => 'Period ' . trim($timetable['period'])));
                $period = $periodStmt->fetchColumn();
                $classStmt->execute(array('code' => $timetable['classCode']));
                $class = $classStmt->fetchColumn();

                // got the period and class IDs, go ahead and insert the timetabling
                if($period && $class) {
                    $timetableStmt->execute(array(
                        'classID' => $class,
                        'periodID' => $period,
                        'room' => $timetable['room'],
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
