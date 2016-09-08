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
    /**
     * @var resource Contains DB connection string
     */
    private $db;

    /**
     * @var string Contains log of check output
     */
    private $log;

    public function __construct($db = '')
    {
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
        return $this->log;
    }

    /* all private methods with 'Schoolbox' or the current db name will be picked up by the reflection in run() */

    private function MssqlConnection()
    {
        $this->log .= "  MSSQL : ";
        $db = new MSSQL();
        $db->debug = true;
        $result = $db->query("SELECT @@VERSION");
        if ($result) {
            $this->log .= "OK\n";
        } else {
            $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
        }
        unset($db,$result);
    }

    /**
     * Gets connection to A+ database.
     *
     * @return MSSQL
     */
    private function getMssqlAPlusConnection()
    {
        $db = new MSSQL(
            Config::get('external_humanedge_aplus_db_db'),
            [
                'host'     => Config::get('external_humanedge_aplus_db_host'),
                'user'     => Config::get('external_humanedge_aplus_db_user'),
                'passwd'   => Config::get('external_humanedge_aplus_db_passwd'),
                'db'       => Config::get('external_humanedge_aplus_db_db'),
                'encoding' => Config::get('external_db_encoding'),
            ]
        );
        $db->debug = true;
        return $db;
    }

    private function Db2Connection()
    {
        $this->log .= "  DB2 : ";
        $eec = new External_Edumate_Connection();
        $db = $eec->getDatabase();
        $result = $db->query("SELECT * FROM TABLE(SYSPROC.ENV_GET_INST_INFO());");

        $this->log .= $result ? "OK\n" : "\n    Failed - ".db2_conn_errormsg()."\n";
    }

    private function EdumateCheckConnection()
    {
        $this->log .= "\nEdumate Connection Check\n";
        $this->Db2Connection();
    }

    private function TassCheckConnection()
    {
        $this->log .= "\nTass Connection Check\n";
        $this->MssqlConnection();
    }

    private function SynergeticCheckConnection()
    {
        $this->log .= "\nSynergetic Connection Check\n";
        $this->MssqlConnection();
    }

    private function SchoolproCheckConnection()
    {
        $this->log .= "\nSchoolpro Connection Check\n";
        $this->MssqlConnection();
    }

    private function MazeCheckConnection()
    {
        $this->log .= "\nMaze Connection Check\n";
        $this->MssqlConnection();
    }

    private function PcschoolsCheckConnection()
    {
        $this->log .= "\nPC School Connection Check\n";
        $this->MssqlConnection();
    }

    private function HumanedgeCheckConnection()
    {
        $this->log .= "\nHumanedge Connection Check\n";
        $this->log .= "  MSSQL (SAS connection) : ";
        $this->MssqlConnection();
        $this->log .= "  MSSQL (A+ connection) : ";
        $db = $this->getMssqlAPlusConnection();
        $result = $db->query("SELECT @@VERSION");
        if ($result) {
            $this->log .= "OK\n";
        } else {
            $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
        }
        unset($db,$result);
    }

    /**
     * Check the Denbigh2 API method access and see if anything snaps
     */
    private function Denbigh2CheckConnection()
    {
        $this->log .= "\nDenbigh2 Connnection Check\n";

        $list[] = 'Enrolments';
        $list[] = 'Students';
        $list[] = 'Classes';
        $list[] = 'PeriodTimes';
        $list[] = 'StudentTimetable';
        $list[] = 'TimetableLessons';

        foreach($list as $currentApi) {
            $api = new External_Denbigh2_Api($currentApi, array());

            if (!$api->hasErrors()) {
                $this->log .= "OK\n";
            } else {
                $error = $api->getErrors();
                $firstError = $error[0];

                $this->log .= "\n    Failed - {$firstError}\n";
            }
        }
    }

    private function TassCheckTables()
    {
        $this->log .= "\nTass Permission Check - Tables\n";
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
        $list[] = 'portalusers';
        $list[] = 'studboard';
        $list[] = 'tel_vw_telemf';
	$list[] = 'ttparms';
	$list[] = 'family';
	$list[] = 'persondefs';
        sort($list);

        foreach ($list as $table) {
            $this->log .= "  $table : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $table");
            if ($result) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
    }

    private function MazeCheckTables()
    {
        $this->log .= "\nMaze Permission Check - Tables\n";
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
            $this->log .= "  $table : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $table");
            if ($result) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
    }

    private function PcschoolsCheckTables()
    {
        $this->log .= "\nPC School Permission Check - Tables\n";
        $list[] = 'TIMEFILE';
        $list[] = 'SUB';
        $list[] = 'SUBJECT';
        $list[] = 'STUDENT';
        $list[] = 'TIMEPDHD';
        $list[] = 'CALENDAR';
        $list[] = 'CODEMAST';
        $list[] = 'TEACHER';
        $list[] = 'FAMILY';
        $list[] = 'SYSFILE';
        $list[] = 'IDENTITY';
        sort($list);

        foreach ($list as $table) {
            $this->log .= "  $table : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM [$table]");
            if ($result) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
    }

    private function PcschoolsCheckStoredProcedures()
    {
        $this->log .= "\nPC School Permission Check - Stored Procedures\n";
        $db = new MSSQL();
        $db->debug = true;
        $splist[] = array(
            'spSelTimetable',
            array(
                array('@DATE',           date('Ymd'),          SQLVARCHAR, false, false, -1),
                array('@TIMEPERIOD',     '',                   SQLVARCHAR, false, false, -1),
                array('@STUDKEY',        '',                   SQLVARCHAR, false, false, -1),
                array('@TEACHERCODE',    '',                   SQLVARCHAR, false, false, -1),
            )
        );

        foreach ($splist as $sp) {
            $this->executeProcedure($db, $sp[0], $sp[1]);
        }
    }

    private function SynergeticCheckTables()
    {
        $this->log .= "\nSynergetic Permission Check - Tables\n";
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

        // Pastoral Care Tables

        if (Config::get('pastoral_enabled')) {
            $list[] = 'luPastoralCareCategory';
            $list[] = 'luPastoralCareType';
            $list[] = 'PastoralCareComments';
            $list[] = 'PastoralCareContacts';
            $list[] = 'PastoralCareActions';
            $list[] = 'tPastoralCare';
        }

        sort($list);

        foreach ($list as $table) {
            $this->log .= "  $table : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $table");
            if ($result) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
    }

    private function SynergeticCheckViews()
    {
        $this->log .= "\nSynergetic Permission Check - Views\n";
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
            $this->log .= "  $view : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $view");
            if ($result) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
    }

    private function SynergeticCheckStoredProcedures()
    {
        $this->log .= "\nSynergetic Permission Check - Stored Procedures\n";
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
        $this->log .= "\nSynergetic Permission Check - Markbook - Tables (Optional)\n";
        $list[] = 'StaffAssessmentMarkBook';
        $list[] = 'StudentAssessmentMarkBook';
        sort($list);

        foreach ($list as $table) {
            $this->log .= "  $table : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $table");
            if ($result) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
        unset($list);
    }

    private function SynergeticCheckCommunityPortal()
    {
        $this->log .= "\nSynergetic Permission Check - Community Portal - Tables (Optional)\n";
        $this->log .= "  CommunityLogins : ";
        $db = new MSSQL();
        $db->debug = true;
        $result = $db->query("UPDATE CommunityLogins SET Password = '' WHERE 1 = 0");
        if ($result) {
            $this->log .= "OK\n";
        } else {
            $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
        }
        unset($db,$result);
    }

    private function SynergeticCheckAttendance()
    {
        // Attendance Specifc (Optional)
        $this->log .= "\nSynergetic Permission Check - Attendance - Tables (Optional)\n";
        $list[] = 'luAbsenceReason';
        $list[] = 'luAbsenceType';
        $list[] = 'TimetableSubstitutes';
        $list[] = 'wkAttendances';
        $list[] = 'AttendanceMaster'; // not listed on wiki - not sure if required
        $list[] = 'tAttendances'; // not listed on wiki - not sure if required
        sort($list);

        foreach ($list as $table) {
            $this->log .= "  $table : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $table");
            if ($result) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
        unset($list);

        $this->log .= "\nSynergetic Permission Check - Attendance - Views (Optional)\n";
        $list[] = 'pvTimetableDefinitionAll';
        sort($list);

        foreach ($list as $view) {
            $this->log .= "  $view : ";
            $db = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $view");
            if ($result) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db,$result);
        }
        unset($list);

        $this->log .= "\nSynergetic Permission Check - Attendance - Stored Procedures (Optional)\n";
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
            'spswkAttendances',
            array(
                array('@wkAttendanceMasterSeq', 1, SQLINT4, false, false, -1),
                array('@FillActualAbsenceColumnsFlag', 1, SQLINT4),
                array('@CheckPossibleNonAttendFlag', 1, SQLINT4),
                array('@CalculateTodaysAttendanceStringFlag', 1, SQLINT4),
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
    * Test chub
    */
    private function SchoolproCheckStoredProcedures()
    {
        $this->log .= "\nSchoolpro Permission Check - Stored Procedures\n";
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
        $this->log .= "\nEdumate Permission Check - Views\n";
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
            $this->log .= "  $viewname : ";
            $result = $db->query("SELECT * FROM $viewname FETCH FIRST 1 ROWS ONLY");
            if ($result) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "\n    Failed - ".db2_stmt_errormsg()."\n";
            }
        }

    }

    /**
     * Check HumanEdge SAS2000 tables
     */
    private function HumanedgeCheckSasTables()
    {
        $this->log .= "\nHumanedge Permission Check - SAS Tables\n";
        $tables = [
            'Contact',
            'Staff',
            'Student',
            'StudentContact',
            'vw_Contact_Addresses',
        ];
        foreach ($tables as $table) {
            $this->log .= "  $table : ";
            $db        = new MSSQL();
            $db->debug = true;
            $result = $db->query("SELECT TOP 1 * FROM $table");
            if ($result) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db, $result);
        }
    }

    /**
     * Check HumanEdge A+ tables
     */
    private function HumanedgeCheckAPlusTables()
    {
        $this->log .= "\nHumanedge Permission Check - SAS Tables\n";
        $tables = [
            'tblAllocation',
            'tblBlockItem',
            'tblCalendarDay',
            'tblDay',
            'tblPeriod',
            'tblPeriodTimesByDay',
            'tblRoom',
            'tblStudent',
            'tblSubject',
            'tblSubjectChoice',
            'tblTeacher',
        ];
        foreach ($tables as $table) {
            $this->log .= "  $table : ";
            $db     = $this->getMssqlAPlusConnection();
            $result = $db->query("SELECT TOP 1 * FROM $table");
            if ($result) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
            }
            unset($db, $result);
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
        $this->log .= "  ".$proc." : ";
        if ($result['success']) {
            if ($result['message']) {
                $this->log .= "OK - {$result['message']}\n";
            } else {
                $this->log .= "OK\n";
            }
        } else {
            $this->log .= "\n    Failed - ".mssql_get_last_message()."\n";
        }
    }
}
