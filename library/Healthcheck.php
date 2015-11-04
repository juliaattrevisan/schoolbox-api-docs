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
class Healthcheck
{

    private $school;
    private $db;

    public function __construct($school, $db = '')
    {
        $this->school = $school;
        $this->db = ($db) ? $db : ucfirst(strtolower(Config::get('external_type')));

        // check for remapping the Schoolbox db type to be External (which refers to CSV digestion)
        if ($this->db == 'Schoolbox') {
            $this->db = 'External';
        }
    }

    /**
     * This method is designed to provide information about the permissions of the External database
     */
    public function run()
    {
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

    private function MssqlConnection()
    {
        echo "  MSSQL : ";
        $db = new MSSQL();
        $db->debug = true;
        $result = $db->query("SELECT @@VERSION");
        if ($result) {
            echo "OK\n";
        } else {
            echo "\n    Failed - ".mssql_get_last_message()."\n";
        }
        unset($db,$result);
    }

    private function Db2Connection()
    {
        echo "  DB2 : ";
        $eec = new External_Edumate_Connection();
        $db = $eec->getDatabase();
        $result = $db->query("SELECT * FROM TABLE(SYSPROC.ENV_GET_INST_INFO());");

        echo $result ? "OK\n" : "\n    Failed - ".db2_conn_errormsg()."\n";
    }

    private function EdumateCheckConnection()
    {
        echo "\nEdumate Connection Check\n";
        $this->Db2Connection();
    }

    private function TassCheckConnection()
    {
        echo "\nTass Connection Check\n";
        $this->MssqlConnection();
    }

    private function SynergeticCheckConnection()
    {
        echo "\nSynergetic Connection Check\n";
        $this->MssqlConnection();
    }

    private function SchoolproCheckConnection()
    {
        echo "\nSchoolpro Connection Check\n";
        $this->MssqlConnection();
    }

    private function MazeCheckConnection()
    {
        echo "\nMaze Connection Check\n";
        $this->MssqlConnection();
    }

    /**
     * Check the Denbigh2 API method access and see if anything snaps
     */
    private function Denbigh2CheckConnection()
    {
        echo "\nDenbigh2 Connnection Check\n";

        $list[] = 'Enrolments';
        $list[] = 'Students';
        $list[] = 'Classes';
        $list[] = 'PeriodTimes';
        $list[] = 'StudentTimetable';
        $list[] = 'TimetableLessons';

        foreach($list as $currentApi) {
            $api = new External_Denbigh2_Api($currentApi, array());

            if (!$api->hasErrors()) {
                echo "OK\n";
            } else {
                $error = $api->getErrors();
                $firstError = $error[0];

                echo "\n    Failed - {$firstError}\n";
            }
        }
    }

    private function TassCheckTables()
    {
        echo "\nTass Permission Check - Tables\n";
        $list[] = 'studsub';
        $list[] = 'student';
        $list[] = 'tchsub';
        $list[] = 'subtab';
        $list[] = 'ttperiod';
        $list[] = 'ttdays';
        $list[] = 'ttcalendar';
        $list[] = 'ttmast';
        $list[] = 'paraddress';
        $list[] = 'house';
        $list[] = 'teacher';
        $list[] = 'parent';
        $list[] = 'pia_sfaccess';
        $list[] = 'tchdept';
        sort($list);

        foreach ($list as $table) {
            echo "  $table : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $table");
            if ($result) {
                echo "OK\n";
            } else {
                echo "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
    }

    private function MazeCheckTables()
    {
        echo "\nMaze Permission Check - Tables\n";
        $list[] = 'DF';
        $list[] = 'KCT';
        $list[] = 'KCY';
        $list[] = 'KGC';
        $list[] = 'KGH';
        $list[] = 'KSC';
        $list[] = 'SF';
        $list[] = 'ST';
        $list[] = 'STMA';
        $list[] = 'SU';
        $list[] = 'TCTD';
        $list[] = 'TCTQ';
        $list[] = 'TH';
        $list[] = 'THTQ';
        $list[] = 'TT';
        $list[] = 'TTTG';
        $list[] = 'UM';
        sort($list);

        foreach ($list as $table) {
            echo "  $table : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $table");
            if ($result) {
                echo "OK\n";
            } else {
                echo "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
    }

    private function SynergeticCheckTables()
    {
        echo "\nSynergetic Permission Check - Tables\n";
        $list[] = 'Addresses';
        $list[] = 'CalendarEvents';
        $list[] = 'Community';
        $list[] = 'CommunityAlerts';
        $list[] = 'Constituencies';
        $list[] = 'FileSemesters';
        $list[] = 'luBoarder';
        $list[] = 'luBuilding';
        $list[] = 'luCountry';
        $list[] = 'luDepartment';
        $list[] = 'luFileType';
        $list[] = 'luForm';
        $list[] = 'luHouse';
        $list[] = 'luTutor';
        $list[] = 'luYearLevel';
        $list[] = 'Relationships';
        $list[] = 'StudentClasses';
        $list[] = 'SubjectClasses';
        $list[] = 'SubjectClassStaff';
        $list[] = 'Timetable';
        $list[] = 'TimetableDefinition';
        $list[] = 'TimetableSubstitutes';
        $list[] = 'luTimetableGroup';
        $list[] = 'luDays';
        $list[] = 'StaffSchedule';
        $list[] = 'StaffScheduleStudentClasses';

        sort($list);

        foreach ($list as $table) {
            echo "  $table : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $table");
            if ($result) {
                echo "OK\n";
            } else {
                echo "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
    }

    private function SynergeticCheckViews()
    {
        echo "\nSynergetic Permission Check - Views\n";
        $list[] = 'vCommunityAddresses';
        $list[] = 'vPhotos';
        $list[] = 'vStaff';
        $list[] = 'vStaffJobPositions';
        $list[] = 'vStudentContactAllAddress';
        $list[] = 'vStudents';
        $list[] = 'vSubjectClassesStaff';
        $list[] = 'vStudentClasses';
        sort($list);

        foreach ($list as $view) {
            echo "  $view : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $view");
            if ($result) {
                echo "OK\n";
            } else {
                echo "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
    }

    private function SynergeticCheckStoredProcedures()
    {
        echo "\nSynergetic Permission Check - Stored Procedures\n";
        $date = new Alaress_Date_Format();
        $db = new MSSQL();
        $db->debug = true;
        $splist[] = array(
            'spgTimetableDay',
            array(
                array('@TimeTableGroup', 'SEN',                SQLVARCHAR, false, false, -1),
                array('@Date',           $date->getDatabase(), SQLVARCHAR, false, false, -1),
                array('@CurrentDay',     'day',                SQLINT4,    true,  false, -1),
            )
        );
        $splist[] = array(
            'spsConfigUserDefaultYearSemester',
            array(
                array('@ID','1',SQLINT4,false,false, -1),
            )
        );
        $splist[] = array(
            'spsTimetable',
            array(
                array('@FileYear', $date->getYear(), SQLINT4),
                array('@FileSemester', $date->getYearHalf(), SQLINT4),
                array('@ID', '1', SQLINT4),
                array('@Date', $date->getDatabaseDate(), SQLVARCHAR),
                array('@IncludeStaffScheduleFlag',1, SQLINT4),
                array('@CheckAttendancesExistFlag',1, SQLINT4),
                array('@IncludeSparesFlag', 0, SQLINT4),
                array('@IncludeRecessFlag', 0, SQLINT4),
            )
        );

        foreach ($splist as $sp) {
            $this->executeProcedure($db, $sp[0], $sp[1]);
        }
    }

    private function SynergeticCheckMarkbook()
    {
        // Attendance Specifc (Optional)
        echo "\nSynergetic Permission Check - Markbook - Tables (Optional)\n";
        $list[] = 'StaffAssessmentMarkBook';
        $list[] = 'StudentAssessmentMarkBook';
        sort($list);

        foreach ($list as $table) {
            echo "  $table : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $table");
            if ($result) {
                echo "OK\n";
            } else {
                echo "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
        unset($list);
    }

    private function SynergeticCheckAttendance()
    {
        // Attendance Specifc (Optional)
        echo "\nSynergetic Permission Check - Attendance - Tables (Optional)\n";
        $list[] = 'luAbsenceReason';
        $list[] = 'luAbsenceType';
        $list[] = 'TimetableSubstitutes';
        $list[] = 'wkAttendances';
        $list[] = 'AttendanceMaster'; // not listed on wiki - not sure if required
        $list[] = 'tAttendances'; // not listed on wiki - not sure if required
        sort($list);

        foreach ($list as $table) {
            echo "  $table : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $table");
            if ($result) {
                echo "OK\n";
            } else {
                echo "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
        unset($list);

        echo "\nSynergetic Permission Check - Attendance - Views (Optional)\n";
        $list[] = 'pvTimetableDefinitionAll';
        sort($list);

        foreach ($list as $view) {
            echo "  $view : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $view");
            if ($result) {
                echo "OK\n";
            } else {
                echo "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
        unset($list);

        echo "\nSynergetic Permission Check - Attendance - Stored Procedures (Optional)\n";
        $db = new MSSQL();
        $db->debug = true;
        $list[] = array(
            'spiwkAttendances',
            array(
                array('@FileType', '', SQLVARCHAR, false, false, -1),
                array('@FileYear', 1, SQLINT4, false, false, -1),
                array('@FileSemester', 1, SQLINT4, false, false, -1),
                array('@ClassCampus', '', SQLVARCHAR, false, false, -1),
                array('@ClassCode', '', SQLVARCHAR, false, false, -1),
                array('@AttendanceDate', date('Y-m-d'), SQLVARCHAR, false, false, -1),
                array('@AttendancePeriod', 1, SQLINT4, false, false, -1),
                array('@AttendanceDayNumber', 1, SQLINT4, false, false, -1),
                array('@StaffID', 1, SQLINT4, false, false, -1),
                array('@AttendedFlag', 1, SQLINT4, false, false, -1),
                array('@TimeTableGroup', '', SQLVARCHAR, false, false, -1),
                array('@SynWebFlag', 1, SQLINT4, false, false, -1),
                array('@wkAttendanceMasterSeq', 1, SQLINT4, true, true, -1 ),
            )
        );
        $list[] = array(
            'spswkAttendances_ws',
            array(
                array('@wkAttendanceMasterSeq', 1, SQLINT4, false, false, -1),
                array('@CheckPossibleNonAttendFlag', 1, SQLINT1, false, false, -1),
                array('@OnlyReturnNonAttendedFlag', 1, SQLINT1, false, false, -1),
                array('@OnlyReturnAttendedFlag', 1, SQLINT1, false, false, -1),
                array('@OnlyReturnPossibleNonAttendedButMarkedPresentFlag', 1, SQLINT1, false, false, -1),
                array('@OnlyReturnUnMarkedFlag', 1, SQLINT1, false, false, -1),
                array('@FillActualAbsenceColumnsFlag', 1, SQLINT1, false, false, -1),
                array('@CalculateTodaysAttendanceStringFlag', 1, SQLINT1, false, false, -1),
                array('@OnlyReturnEditedRecordsFlag', 1, SQLINT1, false, false, -1)
            )
        );
        $list[] = array(
            'sptwkAttendances',
            array(
                array('@wkAttendanceMasterSeq', 1, SQLINT4, false, false, -1),
                array('@AttendanceSeq', '', SQLINT4, false, false, -1),
                array('@AttendanceMasterSeq', 'seq', SQLINT4, true, true, -1),
                array('@HoldFlag', 0, SQLINT1, false, false, -1),
                array('@ClassCancelledFlag', 0, SQLINT1, false, false, -1),
                array('@SystemProcessNumber', 0, SQLINT1, false, false, -1),
                array('@AllowMultiPeriodFlag', 0, SQLINT1, false, false, -1),
                array('@DebugPrintFlag', 0, SQLINT1, false, false, -1),
                array('@GroupCount', 0, SQLINT4, true, true, -1)
            )
        );
        $list[] = array(
            'fnTimeStr',
            array(
                array('@ADateTime', date('Y-m-d'), SQLVARCHAR, false, false, -1),
            )
        );

        foreach ($list as $sp) {
            $this->executeProcedure($db, $sp[0], $sp[1]);
        }
        unset($list);
    }

    /**
    * Test maze
    */
    private function MazeCheckStoredProcedures()
    {
        echo "\nMaze Permission Check - Stored Procedures\n";
        $db = new MSSQL();
        $db->debug = true;
        $splist[] = array(
         'klp_GetTimetableStructure',
         array(
             array('@Today', date('Y-m-d'), SQLVARCHAR, false, true, -1),
             array('@ReturnClassPeriodsOnly', 'Y', SQLVARCHAR, false, false, -1),
         )
        );
        $splist[] = array(
         'klp_GetTimetable',
         array(
             array('@Today', date('Y-m-d H:i:s'), SQLVARCHAR, false, false, -1),
             array('@ReturnClassPeriodsOnly', 'Y', SQLVARCHAR, false, false, -1),
         )
        );
        foreach ($splist as $sp) {
            $this->executeProcedure($db, $sp[0], $sp[1]);
        }
    }

    /**
    * Test chub
    */
    private function SchoolproCheckStoredProcedures()
    {
        echo "\nSchoolpro Permission Check - Stored Procedures\n";
        $db = new MSSQL();
        $db->debug = true;
        $splist[] = array(
         'sbGetParent',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pSchoolNum', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetStudent',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pSchoolNum', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetStudentSubjectList',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pSchoolNum', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetStaffSubjectList',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pSchoolNum', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetEnrolledStudents',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pSchoolNum', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetTimetableDefinition',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pCampusID', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetDayNumber',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pCampusID', 1, SQLINT4, false, false, -1),
             array('@pDateToUse', '2013-12-02 12:12:12', SQLVARCHAR, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetPeriodNumber',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pCampusID', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetStaffDayTimetable',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pSchoolNum', 1, SQLINT4, false, false, -1),
             array('@pCampusID', 1, SQLINT4, false, false, -1),
             array('@pDayNumber', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetStudentDayTimetable',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pSchoolNum', 1, SQLINT4, false, false, -1),
             array('@pCampusID', 1, SQLINT4, false, false, -1),
             array('@pDayNumber', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetStudents',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pClassID', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetTeacherName',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pClassID', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetTimetableDefinition',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pCampusID', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetStudentContact',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pSchoolNum', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetPortraitLastUpdated',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pSchoolNum', 1, SQLINT4, false, false, -1),
         )
        );
        $splist[] = array(
         'sbGetPortraitFile',
         array(
             array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
             array('@pSchoolNum', 1, SQLINT4, false, false, -1),
         )
        );
        foreach ($splist as $sp) {
            $this->executeProcedure($db, $sp[0], $sp[1]);
        }
    }

    private function EdumateCheckViews()
    {
        echo "\nEdumate Permission Check - Views\n";
        $list[] = 'classes';
        $list[] = 'parents';
        $list[] = 'report_periods';
        $list[] = 'studentclass';
        $list[] = 'students';
        $list[] = 'teacherclass';
        $list[] = 'parent_student';
        $list[] = 'staff';
        $list[] = 'timetable';
        $list[] = 'timetable_definition';
        sort($list);

        $eec = new External_Edumate_Connection();
        $db = $eec->getDatabase();

        foreach ($list as $view) {
            $viewname = "db2inst1.schoolbox_$view";
            echo "  $viewname : ";
            $result = $db->query("SELECT * FROM $viewname FETCH FIRST 1 ROWS ONLY");
            if ($result) {
                echo "OK\n";
            } else {
                echo "\n    Failed - ".db2_stmt_errormsg()."\n";
            }
        }

    }

    /**
     * Runs a stored proc, outputting relevant output
     *
     * @param MSSQL $db
     * @param       $proc
     * @param       $params
     *
     * @return void
     */
    private function executeProcedure(MSSQL $db, $proc, $params)
    {
        $result = $db->executeProcedure($proc, $params);
        echo "  ".$proc." : ";
        if ($result['success']) {
            if ($result['message']) {
                echo "OK - {$result['message']}\n";
            } else {
                echo "OK\n";
            }
        } else {
            echo "\n    Failed - ".mssql_get_last_message()."\n";
        }
    }
}
