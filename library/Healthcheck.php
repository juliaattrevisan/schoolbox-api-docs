<?php
/**
 * CLI-facing object to perform a health check on external database permissions
 *
 * Notes: hacked from the sync->diagnose() configuration by Matt Day
 *
 * @author      Sam Bailey <sam.bailey@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Base as BaseStatement;
use Schoolbox\IConfigLoader;

class Healthcheck
{
    /** @var string $db External database connector type */
    private $externalType;

    /** @var string $log Contains log of check output */
    private $log = '';

    /** @var string $errors */
    private $errors = [];

    /** @var IConnector */
    private $connector;

    /** @var IConnector[] $secondaryConnectors Array of secondary database connectors */
    private $secondaryConnectors = [];

    /** @var array This is an array used to hold the output parameters of procedure calls */
    private $outputParameters = [];

    /** @var IConfigLoader  */
    private $configMapper;

    /**
     * Constructor
     *
     * @param IConnector $databaseExternal
     * @param IConfigLoader $configMapper
     * @throws Exception
     */
    public function __construct($databaseExternal, $configMapper)
    {
        $this->connector = $databaseExternal;
        $this->configMapper = $configMapper;
        $this->externalType = $configMapper->get('external_type');
    }

    /**
     * This method is designed to provide information about the permissions of the External database
     */
    public function run()
    {
        // To prevent doing anything unintended to the clients external SIS DB we need to wrap the healthcheck
        // in a transaction which we will rollback- thus we should only proceed if transaction support is
        // available on all external connections

        /** @var bool $transactionsSupported */
        $transactionsSupported = true;

        // Make sure all databases connected correctly

        if ($this->connector == null) {
            $this->log .= "ERROR: Unable to connect to database '{$this->connector->getDatabase()}' (Hostname: {$this->connector->getHostname()})\n";
        }

        // Attempt to being transactions on all connections

        if (!$this->connector->beginTransaction()) {
            // Transactions not supported

            $this->log .= "ERROR: Database ({$connector->getDatabase()}) does not support transactions";
            $transactionsSupported = false;
        }

        /** @var IConnector $connector */
        foreach ($this->secondaryConnectors as $connector) {
            if ($connector == null) {
                $this->log .= "ERROR: Unable to connect to database '{$connector->getDatabase()}' (Hostname: {$connector->getHostname()})\n";
            } else {
                if (!$connector->beginTransaction()) {
                    // Transactions not supported

                    $this->log .= 'ERROR: Database ({$connector->getDatabase()}) does not support transactions';
                    $transactionsSupported = false;
                }
            }
        }

        // Only proceed if database transactions could be successfully started on all connectors

        if ($transactionsSupported) {

            $testFunctionName = "{$this->externalType}Test";

            if (method_exists($this, $testFunctionName)) {
                $this->log .= str_pad(str_pad("METHOD", 15) . "DATABASE OBJECT", 50) . "TEST RESULT\n";
                $this->log .= "------------------------------------------------------------------------------------------------------------------\n";
                $this->$testFunctionName();
            } else {
                $this->errors[] = "Sorry, no health check is currently available for this external connector.";
            }
        }

        // Rollback any transactions we were able to start so we don't end up inserting anything into the SIS

        if ($this->connector->inTransaction()) {
            $this->connector->rollback();
        }

        /** @var IConnector $connector */
        foreach ($this->secondaryConnectors as $connector) {
            if ($connector != null) {
                if ($connector->inTransaction()) {
                    $connector->rollBack();
                }
            }
        };

        // Return the results

        return [
            'log' => trim($this->log),
            'errors' => $this->errors,
            'externalType' => $this->externalType
        ];
    }

    /**
     * Test a query against each database object in the supplied array
     *
     * @param array $objects
     * @param string $db If provided, run against this instead of the default DB
     * @param string $schema If provided, run against this instead of the default schema
     * @param string $query If provided, run against this instead of default select first query
     */
    private function selectTest($objects, $db = null, $schema = null, $query = "SELECT TOP 1 * FROM :object")
    {
        sort($objects);

        $methods = (explode(' ', trim($query)));
        $method  = $methods[0];

        // Build an object name
        $objectNamePrefix  = '';
        $objectParamPrefix = '';
        if (isset($db)) {
            $objectNamePrefix  .= $db . '.';
            $objectParamPrefix .= $this->connector->quoteIdentifier($db) . '.';
        }
        if (isset($schema)) {
            $objectNamePrefix  .= $schema . '.';
            $objectParamPrefix .= $this->connector->quoteIdentifier($schema) . '.';
        }

        foreach ($objects as $object) {
            // Build reporting and escaped object name
            $objectName  = $objectNamePrefix . $object;
            $objectParam = $objectParamPrefix . $this->connector->quoteIdentifier($object);

            $this->log .= str_pad(str_pad($method, 15) . $objectName, 50);

            $sql = str_replace(':object', $objectParam, $query);
            $statement = $this->connector->prepareQuery($sql);

            try {
                $statement->execute();
                $this->log .= "OK\n";
            } catch (\Alaress\Database\Exception $e) {
                $this->log .= "FAILED\n";
                $this->errors[] = "$sql\n{$e->getMessage()}";
            }
        }
    }

    /**
     * Test a list of database functions.
     *
     * @param array $objects
     */
    private function functionTest($objects)
    {
        $this->executeTest($objects, 'FUNCTION');
    }

    /**
     * Test a list of stored procedures.
     *
     * @param array $objects
     */
    private function procedureTest($objects)
    {
        $this->executeTest($objects, 'EXECUTE');
    }

    /**
     * Test object execution against each database object in the supplied array.
     *
     * This now uses HAS_PERMS_BY_NAME instead of actually running the proc;
     * this will be more efficient and less destructive.
     *
     * TODO this works only against MSSQL; may need a method for testing other DB procs
     *
     * @param array  $objects
     * @param string $objectType The type of object to test; this only affects logs.
     */
    private function executeTest($objects, $objectType)
    {
        sort($objects);

        $outputValues = [];

        $statement = $this->connector->prepareQuery("SELECT HAS_PERMS_BY_NAME(:proc, 'OBJECT', 'EXECUTE')");

        foreach ($objects as $object) {

            $this->log .= str_pad(str_pad($objectType, 15). $object, 50);

            try {
                $statement->execute(['proc' => $object]);
                $result = $statement->fetchColumn();
                $statement->close();

                // I want to be able to query if the object exists or not;
                // however, all attempts at doing so have failed.
                if (empty($result)) {
                    $this->log .= "FAILED - object does not exist, or user does not have EXECUTE permission on it\n";
                } else {
                    $this->log .= "OK\n";
                }
            } catch (\Alaress\Database\Exception $e) {

                $error = "EXECUTE {$object}\n{$e->getMessage()}\n";

                $error .= "\n{$statement->getSql()}\n";

                $this->errors[] = $error;
                $this->log .= "FAILED\n";
            }
        }

        unset($outputValues);
    }

    // Connector specific test functions, ignore any unused function warnings as these are called dynamically

    /** @noinspection PhpUnusedPrivateMethodInspection */
    public function edumateTest()
    {
        $tables = [
            'studsub',
            'student',
            'tchsub',
            'subtab',
            'ttperiod',
            'ttdays',
            'ttcalendar',
            'ttmast',
            'paraddress',
            'house',
            'teacher',
            'parent',
            'pia_sfaccess',
            'tchdept',
            'portalusers',
            'studboard',
            'tel_vw_telemf',
            'ttparms',
            'family',
            'persondefs',
            'studcommrules',
        ];

        $this->selectTest($tables, null, null, "SELECT * FROM :object FETCH FIRST 1 ROWS ONLY");

        $db2inst1Tables = [
            'schoolbox_classes',
            'schoolbox_parents',
            'schoolbox_report_periods',
            'schoolbox_studentclass',
            'schoolbox_students',
            'schoolbox_teacherclass',
            'schoolbox_parent_student',
            'schoolbox_staff',
            'schoolbox_timetable',
            'schoolbox_timetable_definition'
        ];

        $this->selectTest($db2inst1Tables, null, 'db2inst1', "SELECT * FROM :object FETCH FIRST 1 ROWS ONLY");
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function mazeTest()
    {
        $tables = [
            'DF',
            'KCT',
            'KCY',
            'KGC',
            'KGH',
            'KSC',
            'SF',
            'ST',
            'STMA',
            'SU',
            'TCTD',
            'TCTQ',
            'TH',
            'THTQ',
            'TT',
            'TTTG',
            'UM'
        ];

        $this->selectTest($tables);
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function tassTest() {
        $tables = [
            'studsub',
            'student',
            'tchsub',
            'subtab',
            'ttperiod',
            'ttdays',
            'ttcalendar',
            'ttmast',
            'paraddress',
            'house',
            'teacher',
            'parent',
            'pia_sfaccess',
            'tchdept',
            'portalusers',
            'studboard',
            'tel_vw_telemf',
            'ttparms',
            'family',
            'persondefs',
            'studcommrules',
            'eventcalendar',
            'eventcatdefs',
            'eventtype'
        ];
        $this->selectTest($tables);
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function pcschoolsTest()
    {
        $tables = [
            'TIMEFILE',
            'SUB',
            'SUBJECT',
            'STUDENT',
            'TIMEPDHD',
            'CALENDAR',
            'CODEMAST',
            'TEACHER',
            'FAMILY',
            'SYSFILE',
            'IDENTITY'
        ];

        $this->selectTest($tables);

        $procedures = [
            'spSelTimetable',
        ];

        $this->procedureTest($procedures);
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function synergeticTest()
    {
        $tables = [
            'Addresses',
            'CalendarEvents',
            'Community',
            'CommunityAlerts',
            'Config',
            'Constituencies',
            'FileSemesters',
            'luBoarder',
            'luBuilding',
            'luCountry',
            'luDays',
            'luDepartment',
            'luFileType',
            'luForm',
            'luHouse',
            'luRoom',
            'luReportResultType',
            'luTutor',
            'luYearLevel',
            'Relationships',
            'StudentClasses',
            'SubjectClasses',
            'SubjectClassStaff',
            'Timetable',
            'TimetableDefinition',
            'TimetableSubstitutes',
            'luTimetableGroup',
            'luLocation',
            'luTeam',
            'StaffSchedule',
            'StaffScheduleStudentClasses',
            'vCommunityAddresses',
            'vPhotos',
            'vStaff',
            'vStaffJobPositions',
            'vStudentContactAllAddress',
            'vStudents',
            'vSubjectClassesStaff',
            'vStudentClasses',
            'StaffAssessmentMarkBook',
            'StudentAssessmentMarkBook',
            'luAbsenceReason',
            'luAbsenceType',
            'TimetableSubstitutes',
            'wkAttendances',
            'AttendanceMaster',
            'tAttendances',
            'IndividualPlans',
            'StudentYears'
        ];

        // Pastoral Care Tables (Only If Module Enabled)

        if ($this->configMapper->get('pastoral_enabled')) {
            $tables = array_merge($tables, [
                'luPastoralCareCategory',
                'luPastoralCareType',
                'PastoralCareComments',
                'PastoralCareContacts',
                'PastoralCareActions',
                'tPastoralCare'
            ]);
        }

        $this->selectTest($tables);

        // External Document Reports

        if ($this->configMapper->get('external_document_report_enabled')) {
            $externalPing = new \External_Ping();
            if ($externalPing->getVersion() == "68") {
                $db     = null;
                $schema = 'media';
            } else {
                $db     = 'SynergyOneMedia';
                $schema = 'dbo';
            }
            $this->selectTest(
                [
                    'StudentReportsArchiveLink',
                    'StudentReportsArchive',
                    'tDocuments',
                    'DocumentReferences'
                ],
                $db,
                $schema
            );
        }

        // Community Portal Password Update

        $this->selectTest(['CommunityLogins'], null, null, "UPDATE :object SET Password = '' WHERE 1 = 0");

        // Stored Procedures

        $procedures = [
            'spgTimetableDay',
            'spsConfigUserDefaultYearSemester',
            'spsTimetable',
            'spiwkAttendances',
            'spswkAttendances',
            'sptwkAttendances',
            'spiExcursions',
            'spuExcursionDetails',
            'spiCommunityEventsCurrent',
        ];

        $this->procedureTest($procedures);

        $functions = [ 'dbo.fnTimeStr' ];

        $this->functionTest($functions);
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function schoolproTest()
    {
        $procedures = [
            'sbGetParent',
            'sbGetStudent',
            'sbGetStudentSubjectList',
            'sbGetStaffSubjectList',
            'sbGetEnrolledStudents',
            'sbGetTimetableDefinition',
            'sbGetDayNumber',
            'sbGetPeriodNumber',
            'sbGetStaffDayTimetable',
            'sbGetStudentDayTimetable',
            'sbGetStudents',
            'sbGetTeacherName',
            'sbGetTimetableDefinition',
            'sbGetStudentContact',
            'sbGetPortraitLastUpdated',
            'sbGetPortraitFile',
        ];
        $this->procedureTest($procedures);
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function humanedgeTest()
    {
        $tables = [
            'Contact',
            'Staff',
            'Student',
            'StudentContact',
            'vw_Contact_Addresses',
        ];

        $this->selectTest($tables);

        /**
         * Check HumanEdge A+ tables
         *
         * private
         * function HumanedgeCheckAPlusTables()
         * {
         * $this->log .= "\nHumanedge Permission Check - SAS Tables\n";
         * $tables = [
         * 'tblAllocation',
         * 'tblBlockItem',
         * 'tblCalendarDay',
         * 'tblDay',
         * 'tblPeriod',
         * 'tblPeriodTimesByDay',
         * 'tblRoom',
         * 'tblStudent',
         * 'tblSubject',
         * 'tblSubjectChoice',
         * 'tblTeacher',
         * ];
         * foreach ($tables as $table) {
         * $this->log .= "  $table : ";
         *
         * $result = $this->secondaryConnectors[$this->configMapper->get('external_humanedge_aplus_db_db')]->query("SELECT TOP 1 * FROM $table");
         *
         * if ($result) {
         * $this->log .= "OK\n";
         * } else {
         * $this->log .= "\n    Failed - " . mssql_get_last_message() . "\n";
         * }
         * unset($db, $result);
         * }
         * }
         */
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function schooledgeTest()
    {
        $tables = [
            'Contact',
            'Staff',
            'Student',
            'StudentContact',
            'vw_Contact_Addresses',
        ];

        $this->selectTest($tables);


        $fcTables = [
            "TimetableSubject",
            "TimetableSubjectChoice",
            "Timetable",
            "TimetableClass",
            "YearLevelSub",
            "YearLevelSubStudent",
            "TimetableClassInstance",
            "TimetableClassStaff",
            "TimetableClassInstanceStaff",
            "TimetableClassRoom",
            "Room",
            "TimetableClassInstanceRoom",
            "TimetableDay",
            "TimetablePeriod",
            "TimetableDate",
            "TimetableBlockInstance",
        ];

        $this->selectTest($fcTables, null, 'fc');

        $this->selectTest(
            ['periodExpand'],
            null,
            'fc',
            'SELECT TOP 1 * FROM fc.TimetableBlockInstance tbi CROSS APPLY :object(tbi.TimetablePeriodID, tbi.Consecutive) tpe'
        );
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function doublefirstTest() {
        $tables = [
            "AttendanceBreaks",
            "AttendancePeriods",
            "AttendancePeriodStructure",
            "BatchMaster",
            "ClusterMaster",
            "ContactEmailInformation",
            "ContactMaster",
            "ContactTelephoneInformation",
            "CurrentStaff",
            "LookupDetails",
            "PeriodNames",
            "PupilCareAuthority",
            "PupilContacts",
            "PupilCurrentSchool",
            "PupilMedicalInformation",
            "PupilPhoto",
            "PupilSubjects",
            "SchoolSiteBlock",
            "SchoolSiteBlockRooms",
            "SchoolTerms",
            "StaffEmailInformation",
            "StaffPersonalDetails",
            "StaffTelephones",
            "SubjectMaster",
            "TimetableClassRoom",
            "TimetableClassStaff",
            "TimetableMaster",
            "TimetableMatrix",
            "TimetableParameters",
            "VLContact",
            "VLContactAddress",
            "VLContactEmail",
            "VLContactTelephone",
            "VLPupilContact",
            "VLPupilDetails",
            "VLStaff",
            "V_PupilBasicInformation",
            "YearGroupSubjectDivision",
        ];

        $this->selectTest($tables);
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function isamsCheckTables()
    {
        $list[] = 'V_Class';
        $list[] = 'V_Parent';
        $list[] = 'V_Staff';
        $list[] = 'V_Student';
        $list[] = 'V_TimetableSchedule';
        $list[] = 'V_TimetableStructure';
        $this->selectTest($list);
    }
}
