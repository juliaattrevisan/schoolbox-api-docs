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
     *
     * @throws PDOException Thrown if:
     *                       - db driver does not support transactions,
     *                       - we attempt to begin a transaction when already in a transaction
     *                       - we attempt to rollback a transaction when not in a transaction
     * @throws LogicException If configured external database type is not recognised
     */
    public function run(): void
    {
        // Make sure all databases connected correctly
        if ($this->connector === null) {
            $this->log .= "ERROR: Unable to connect to database '{$this->connector->getDatabase()}' (Hostname: {$this->connector->getHostname()})\n";
            return;
        }

        // To prevent doing anything unintended to the clients external SIS DB we need to wrap the healthcheck
        // in a transaction which we will rollback- thus we should only proceed if transaction support is
        // available on all external connections

        /** @var bool $transactionsSupported */
        $transactionsSupported = true;

        // Attempt to begin transactions on all connections

        if (!$this->connector->beginTransaction()) {
            // Transactions not supported

            $this->log .= "ERROR: Database ({$this->connector->getDatabase()}) does not support transactions";
            $transactionsSupported = false;
        }
        $this->rollbackIfInTransaction($this->connector);

        /** @var IConnector $connector */
        foreach ($this->secondaryConnectors as $connector) {
            if ($connector == null) {
                $this->log .= "ERROR: Unable to connect to database '{$connector->getDatabase()}' (Hostname: {$connector->getHostname()})\n";
            } else {
                if (!$connector->beginTransaction()) {
                    // Transactions not supported

                    $this->log .= "ERROR: Database ({$connector->getDatabase()}) does not support transactions";
                    $transactionsSupported = false;
                }
                $this->rollbackIfInTransaction($connector);
            }
        }

        // Only proceed if database transactions could be successfully started on all connectors
        if ($transactionsSupported) {
            $this->externalTest();
        }
    }


    /**
     * Return the results
     *
     * @return array
     */
    public function results(): array
    {
        return [
            'log'          => trim($this->log),
            'errors'       => $this->errors,
            'externalType' => $this->externalType,
        ];
    }


    /**
     * Execute external database tests
     *
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     * @throws LogicException If configured external database type is not recognised
     */
    private function externalTest(): void
    {
        if(in_array($this->externalType, ['schoolbox', 'denbigh'], true)){
            $this->errors[] = 'Sorry, no health check is currently available for this external connector.';
            return;
        }
        $this->log .= str_pad(str_pad('METHOD', 15) . 'DATABASE OBJECT', 50) . "TEST RESULT\n";
        $this->log .= "------------------------------------------------------------------------------------------------------------------\n";

        switch($this->externalType){
            case 'synergetic':
                $this->synergeticTest();
                break;
            case 'tass':
                $this->tassTest();
                break;
            case 'edumate':
                $this->edumateTest();
                break;
            case 'schoolpro':
                $this->schoolproTest();
                break;
            case 'maze':
                $this->mazeTest();
                break;
            case 'pcschools':
                $this->pcschoolsTest();
                break;
            case 'humanedge':
                $this->humanedgeTest();
                break;
            case 'doublefirst':
                $this->doublefirstTest();
                break;
            case 'schooledge':
                $this->schooledgeTest();
                break;
            case 'isams':
                $this->isamsTest();
                break;
            default:
                throw new LogicException("Unrecognised external type: {$this->externalType}");
        }
    }


    /**
     * Test a query against each database object in the supplied array
     *
     * @param array  $objects
     * @param string $db                If provided, run against this instead of the default DB
     * @param string $schema            If provided, run against this instead of the default schema
     * @param string $query             If provided, run against this instead of default select first query
     * @param bool   $wrapInTransaction Should the query execution be wrapped in a transaction?
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     */
    private function selectTest(
        $objects,
        $db = null,
        $schema = null,
        $query = "SELECT TOP 1 * FROM :object",
        bool $wrapInTransaction = false
    ): void {
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

            if ($wrapInTransaction) {
                $this->beginIfNotInTransaction($this->connector);
            }
            try {
                $statement->execute();
                $this->log .= "OK\n";
            } catch (\Alaress\Database\Exception $e) {
                $this->log .= "FAILED\n";
                $this->errors[] = "$sql\n{$e->getMessage()}";
            }
            if ($wrapInTransaction) {
                $this->rollbackIfInTransaction($this->connector);
            }
        }
    }


    /**
     * Attempts to being a transaction if one is not in progress
     *
     * @param IConnector $connector
     * @throws PDOException If the driver does not support transactions
     */
    private function beginIfNotInTransaction(IConnector $connector): void
    {
        if (!$connector->inTransaction()) {
            $connector->beginTransaction();
        }
    }


    /**
     * Attempts to rollback a transaction if one is in progress
     *
     * @param IConnector $connector
     * @throws PDOException If the driver does not support transactions
     */
    private function rollbackIfInTransaction(IConnector $connector): void
    {
        if ($connector->inTransaction()) {
            $connector->rollBack();
        }
    }

    /**
     * Test a list of database functions.
     *
     * @param array $objects
     */
    private function functionTest($objects): void
    {
        $this->permissionTest($objects, 'EXECUTE');
    }

    /**
     * Test a list of stored procedures.
     *
     * @param array $objects
     */
    private function procedureTest($objects): void
    {
        $this->permissionTest($objects, 'EXECUTE');
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
     * @param string $permission The type of permission to test for
     */
    private function permissionTest($objects, $permission): void
    {
        sort($objects);

        $statement = $this->connector->prepareQuery("SELECT HAS_PERMS_BY_NAME(:object, 'OBJECT', :permission)");

        foreach ($objects as $object) {

            $this->log .= str_pad(str_pad($permission, 15). $object, 50);

            try {
                $statement->execute(['object' => $object, 'permission' => $permission]);
                $result = $statement->fetchColumn();
                $statement->close();

                // I want to be able to query if the object exists or not;
                // however, all attempts at doing so have failed.
                if (empty($result)) {
                    $this->errors[] = sprintf(
                        'Object "%s" does not exist, or user does not have %s permission on it',
                        $object,
                        $permission
                    );
                    $this->log .= "FAILED\n";
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
    }


    /**
     * Returns the names of any database views configured for use by the UPS system
     *
     * @return string[]
     */
    public function customUPSTest(): array
    {
        $tables = array();
        $staff = trim($this->configMapper->get('external_custom_ups_staff'));
        if (strlen($staff) > 0) {
            $tables[] = $staff;
        }
        $student = trim($this->configMapper->get('external_custom_ups_student'));
        if (strlen($student) > 0) {
            $tables[] = $student;
        }
        $parent = trim($this->configMapper->get('external_custom_ups_parent'));
        if (strlen($parent) > 0) {
            $tables[] = $parent;
        }

        return $tables;
    }

    // Connector specific test functions


    /**
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     */
    public function edumateTest(): void
    {
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

    /**
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     */
    private function mazeTest(): void
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

        $tables = array_merge($tables, $this->customUPSTest());
        $this->selectTest($tables);
    }


    /**
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     */
    private function tassTest(): void
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
            'eventcalendar',
            'eventcatdefs',
            'eventtype'
        ];

        $tables = array_merge($tables, $this->customUPSTest());
        $this->selectTest($tables);
    }

    /**
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     */
    private function pcschoolsTest(): void
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

        $tables = array_merge($tables, $this->customUPSTest());
        $this->selectTest($tables);

        $procedures = [
            'spSelTimetable',
        ];

        $this->procedureTest($procedures);
    }


    /**
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     */
    private function synergeticTest(): void
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
            'StudentYears',
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

        $tables = array_merge($tables, $this->customUPSTest());
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
                    'DocumentReferences',
                    'pvDocumentReferences',
                ],
                $db,
                $schema
            );
        }

        // Community Portal Password Update

        $this->selectTest(['CommunityLogins'], null, null, "UPDATE :object SET Password = '' WHERE 1 = 0", true);

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

    private function schoolproTest(): void
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


    /**
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     */
    private function humanedgeTest(): void
    {
        $tables = [
            'Contact',
            'Staff',
            'Student',
            'StudentContact',
            'vw_Contact_Addresses',
        ];

        $tables = array_merge($tables, $this->customUPSTest());
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


    /**
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     */
    private function schooledgeTest(): void
    {
        $tables = [
            'Contact',
            'Staff',
            'Student',
            'StudentContact',
            'vw_Contact_Addresses',
        ];

        $tables = array_merge($tables, $this->customUPSTest());
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


    /**
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     */
    private function doublefirstTest(): void
    {
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

        $tables = array_merge($tables, $this->customUPSTest());
        $this->selectTest($tables);
    }


    /**
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     */
    private function isamsTest(): void
    {
        $list[] = 'V_Class';
        $list[] = 'V_Parent';
        $list[] = 'V_Staff';
        $list[] = 'V_Student';
        $list[] = 'V_TimetableSchedule';
        $list[] = 'V_TimetableStructure';

        $list = array_merge($list, $this->customUPSTest());
        $this->selectTest($list);
    }
}
