<?php
/**
 *
 * CLI-facing object to perform a health check on external database permissions
 *
 * Notes: hacked from the sync->diagnose() configuration by Matt Day
 *
 * @author      Sam Bailey <sam.bailey@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Healthcheck {

    private $school;
    private $db;

    public function __construct($school, $db = '') {
        $this->school = $school;
        $this->db = ($db) ? $db : ucfirst(strtolower(Config::get('external_type')));

        // check for remapping the Schoolbox db type to be External (which refers to CSV digestion)
        if($this->db == 'Schoolbox') {
            $this->db = 'External';
        }
    }

    /**
     * This method is designed to provide information about the permissions of the External database
     */
    public function run() {
        // for the sake of modularity, this is split out into subordinate methods
        $refl = new ReflectionObject($this);
        foreach ($refl->getMethods(ReflectionMethod::IS_PRIVATE) as $m) {
            $meth = $m->name;
            if (strpos($meth, $this->db) !== false || strpos($meth, 'Schoolbox') !== false) {
                $this->$meth();
            }
        }
    }

    /* all private methods with 'Schoolbox' or the current db name will be picked up by the reflection in run() */

    private function SynergeticCheckTables(){
        echo "\nSynergetic Permission Check - Tables\n";
        $list[] = 'Addresses';
        $list[] = 'AttendanceMaster';
        $list[] = 'CalendarEvents';
        $list[] = 'Community';
        $list[] = 'CommunityAlerts';
        $list[] = 'Constituencies';
        $list[] = 'FileSemesters';
        $list[] = 'luAbsenceReason';
        $list[] = 'luAbsenceType';
        $list[] = 'luBuilding';
        $list[] = 'luDepartment';
        $list[] = 'luForm';
        $list[] = 'luHouse';
        $list[] = 'luTutor';
        $list[] = 'luYearLevel';
        $list[] = 'Relationships';
        $list[] = 'StudentClasses';
        $list[] = 'Students';
        $list[] = 'SubjectClasses';
        $list[] = 'SubjectClassStaff';
        $list[] = 'tAttendances';
        $list[] = 'Timetable';
        $list[] = 'TimetableDefinition';
        $list[] = 'luTimetableGroup';
        sort($list);

        foreach($list as $table){
            echo "  $table : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $table");
            if($result){
                echo "OK\n";
            }else{
                echo "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
    }

    private function SynergeticCheckViews(){
        echo "\nSynergetic Permission Check - Views\n";
        $list[] = 'vCommunityAddresses';
        $list[] = 'vPhotos';
        $list[] = 'vStaff';
        $list[] = 'vStaffJobPositions';
        $list[] = 'vStudentContactAllAddress';
        $list[] = 'vStudents';
        $list[] = 'vSubjectClassesStaff';
        sort($list);

        foreach($list as $view){
            echo "  $view : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $view");
            if($result){
                echo "OK\n";
            }else{
                echo "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
    }



}
