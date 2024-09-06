<?php

/**
 * @author      Sam Bailey <sam.bailey@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\HealthCheck;

use Alaress\Database\Connector\IConnector;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Database;
use Schoolbox\IConfigLoader;

/**
 * Performs a health check on external database permissions
 *
 * Notes: hacked from the sync->diagnose() configuration by Matt Day
 */
class Healthcheck
{
    /** @var string $db External database connector type */
    private $externalType;

    /** @var string[] $errors */
    private $errors = [];

    /** @var IConnector */
    private $connector;

    /** @var IConnector[] $secondaryConnectors Array of secondary database connectors */
    private $secondaryConnectors = [];

    /** @var IConfigLoader */
    private $configMapper;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param IConnector|null  $databaseExternal
     * @param Database\Factory $databaseFactory
     * @param IConfigLoader    $configMapper
     * @param LoggerInterface  $logger
     * @throws \InvalidArgumentException
     * @throws \Alaress\Database\Exception
     */
    public function __construct(
        ?IConnector $databaseExternal,
        Database\Factory $databaseFactory,
        IConfigLoader $configMapper,
        LoggerInterface $logger
    ) {
        $this->logger     = $logger;
        $databaseExternal = $databaseExternal ?? $databaseFactory->getExternalDatabase($configMapper, true);

        // Ensure we can connect to external database
        if (!$databaseExternal || ($databaseExternal instanceof \Alaress\Database\Connector\NullConnector)) {
            throw new \InvalidArgumentException('Unable to connect to external database');
        }

        $this->connector    = $databaseExternal;
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
     * @throws \LogicException If configured external database type is not recognised
     */
    public function run(): void
    {
        // To prevent doing anything unintended to the clients external SIS DB we need to wrap the healthcheck
        // in a transaction which we will rollback- thus we should only proceed if transaction support is
        // available on all external connections

        /** @var bool $transactionsSupported */
        $transactionsSupported = true;

        // Attempt to begin transactions on all connections

        if (!$this->connector->beginTransaction()) {
            // Transactions not supported
            $this->logger->warning("ERROR: Database ({$this->connector->getDatabase()}) does not support transactions");
            $transactionsSupported = false;
        }
        $this->rollbackIfInTransaction($this->connector);

        /** @var IConnector $connector */
        foreach ($this->secondaryConnectors as $connector) {
            if ($connector == null) {
                $this->logger->warning("ERROR: Unable to connect to database '{$connector->getDatabase()}' (Hostname: {$connector->getHostname()})");
            } else {
                if (!$connector->beginTransaction()) {
                    // Transactions not supported

                    $this->logger->warning("ERROR: Database ({$connector->getDatabase()}) does not support transactions");
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
            'errors'       => $this->errors,
            'externalType' => $this->externalType,
        ];
    }

    /**
     * Execute external database tests
     *
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     * @throws \LogicException If configured external database type is not recognised
     */
    private function externalTest(): void
    {
        if ($this->externalType === 'schoolbox') {
            $this->errors[] = 'Sorry, no health check is currently available for this external connector.';
            return;
        }
        $this->logger->info(str_pad(str_pad('METHOD', 15) . 'DATABASE OBJECT', 50) . "TEST RESULT");
        $this->logger->info("------------------------------------------------------------------------------------------------------------------");

        switch ($this->externalType) {
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
            case 'pcschools':
                $this->pcschoolsTest();
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
                throw new \LogicException("Unrecognised external type: {$this->externalType}");
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
        $quoter            = $this->connector->getQuoter();
        if (isset($db)) {
            $objectNamePrefix  .= $db . '.';
            $objectParamPrefix .= $quoter->quoteIdentifier($db) . '.';
        }
        if (isset($schema)) {
            $objectNamePrefix  .= $schema . '.';
            $objectParamPrefix .= $quoter->quoteIdentifier($schema) . '.';
        }

        foreach ($objects as $object) {
            // Build reporting and escaped object name
            $objectName  = $objectNamePrefix . $object;
            $objectParam = $objectParamPrefix . $quoter->quoteIdentifier($object);

            $log = str_pad(str_pad($method, 15) . $objectName, 50);

            $sql       = str_replace(':object', $objectParam, $query);
            $statement = $this->connector->prepareQuery($sql);

            if ($wrapInTransaction) {
                $this->beginIfNotInTransaction($this->connector);
            }
            try {
                $statement->execute();
                $this->logger->info("$log OK");
            } catch (\Alaress\Database\Exception $e) {
                $this->logger->warning("$log FAILED");
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
     * Test a list of executables (stored procedures or functions).
     *
     * @param array $objects
     */
    private function executeTest($objects): void
    {
        $this->permissionTest($objects, 'EXECUTE');
    }

    /**
     * Test whether a list of tables have INSERT permission.
     *
     * @param array $objects
     */
    private function insertTest($objects): void
    {
        $this->permissionTest($objects, 'INSERT');
    }

    /**
     * Test whether a list of tables have UPDATE permission.
     *
     * @param array $objects
     */
    private function updateTest($objects): void
    {
        $this->permissionTest($objects, 'UPDATE');
    }

    /**
     * Test whether a list of tables have DELETE permission.
     *
     * @param array $objects
     */
    private function deleteTest($objects): void
    {
        $this->permissionTest($objects, 'DELETE');
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
            $log = str_pad(str_pad($permission, 15) . $object, 50);

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
                    $this->logger->warning("$log FAILED");
                } else {
                    $this->logger->info("$log OK");
                }
            } catch (\Alaress\Database\Exception $e) {
                $error = "EXECUTE {$object}\n{$e->getMessage()}\n";

                $error .= "\n{$statement->getSql()}\n";

                $this->errors[] = $error;
                $this->logger->warning("$log FAILED");
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
        $tables = [];
        $staff  = trim($this->configMapper->get('external_custom_ups_staff'));
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

    // Connector specific test functions.

    /**
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     */
    public function edumateTest(): void
    {
        $db2inst1Tables = [
            'SCHOOLBOX_CLASSES',
            'SCHOOLBOX_PARENTS',
            'SCHOOLBOX_REPORT_PERIODS',
            'SCHOOLBOX_STUDENTCLASS',
            'SCHOOLBOX_STUDENTS',
            'SCHOOLBOX_TEACHERCLASS',
            'SCHOOLBOX_PARENT_STUDENT',
            'SCHOOLBOX_STAFF',
            'SCHOOLBOX_TIMETABLE',
            'SCHOOLBOX_TIMETABLE_DAYS',
            'SCHOOLBOX_TIMETABLE_DEFINITION',
            'EVENT',
            'EVENT_STUDENT',
            'EVENT_STAFF',
            'STUDENT',
            'STAFF',
            'EVENT_TYPE',
            'EVENT_ROOM',
            'ROOM'
        ];

        $this->selectTest($db2inst1Tables, null, null, "SELECT * FROM :object FETCH FIRST 1 ROWS ONLY");
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
            'eventtype',
            'tchcampus',

            /* Studies Code equivalents across the nation
             */
            'studnswbos',
            'studqcaa',
            'studscsa',
            'studvsn',
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
            'ALUMREL',
            'CALENDAR',
            'CODEMAST',
            'FAMILY',
            'IDENTITY',
            'STUDENT',
            'SUB',
            'SUBJECT',
            'SYSFILE',
            'TEACHER',
            'TIMEFILE',
            'TIMEPDHD',
            'TTABLE',
            'WORKAREA',
        ];

        $tables = array_merge($tables, $this->customUPSTest());
        $this->selectTest($tables);
        $this->executeTest(['spSelTimeTable']); // Test stored procedures.
    }

    /**
     * @throws PDOException If transactions not supported and we attempt to begin or rollback a transaction
     */
    private function synergeticTest(): void
    {
        $tables = [
            'Addresses',
            'AttendanceMaster',
            'CalendarEvents',
            'Community',
            'CommunityAlerts',
            'Config',
            'Constituencies',
            'FileSemesters',
            'IndividualPlans',
            'Relationships',
            'StaffAssessmentMarkBook',
            'StaffSchedule',
            'StaffScheduleStudentClasses',
            'StudentAssessmentMarkBook',
            'StudentClasses',
            'StudentContactsPortal',
            'StudentYears',
            'SubjectClassStaff',
            'SubjectClasses',
            'Timetable',
            'TimetableDefinition',
            'TimetableSubstitutes',
            'luAbsenceReason',
            'luAbsenceType',
            'luBoarder',
            'luBuilding',
            'luCountry',
            'luDays',
            'luDepartment',
            'luDocumentClassification',
            'luFileType',
            'luForm',
            'luHouse',
            'luLocation',
            'luReportResultType',
            'luRoom',
            'luStudentStatus',
            'luTeam',
            'luTimetableGroup',
            'luTutor',
            'luYearLevel',
            'tAttendances',
            'vCommunityAddresses',
            'vPhotos',
            'vStaff',
            'vStaffJobPositions',
            'vStudentClasses',
            'vStudentContactAllAddress',
            'vStudents',
            'vStudentsAll',
            'vSubjectClassesStaff',
            'wkAttendances',
        ];

        // Pastoral care tables (only if module enabled).
        if ($this->configMapper->get(ConfigMapper::NAME_PASTORAL_ENABLED)) {
            $allPastoralTables = [
                'luPastoralCareCategory',
                'luPastoralCareType',
                'PastoralCareComments',
                'PastoralCareContacts',
                'PastoralCareActions',
                'tPastoralCare',
            ];
            $writablePastoralTables = [
                'PastoralCareComments',
                'PastoralCareContacts',
                'tPastoralCare',
            ];
            if ($this->configMapper->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
                $allPastoralTables[]      = 'luPastoralCareAction';
                $allPastoralTables[]      = 'luPastoralCareActionType';
                $writablePastoralTables[] = 'PastoralCareActions';
            }
            $this->selectTest($allPastoralTables);
            $this->insertTest($writablePastoralTables);
            if ($this->configMapper->get(ConfigMapper::NAME_PASTORAL_EXTERNAL_DELETE)) {
                $this->deleteTest($writablePastoralTables);
            }
        }

        $tables = array_merge($tables, $this->customUPSTest());
        $this->selectTest($tables);

        // Attendance update Tests.
        $this->updateTest(['wkAttendances']);
        $this->updateTest(['AttendanceMaster']);

        // Markbook tests.
        if ($this->configMapper->get('external_markbook')) {
            $markbookTables = ['StaffAssessmentMarkBook', 'StudentAssessmentMarkBook'];
            $this->updateTest($markbookTables);
            $this->insertTest($markbookTables);
            $this->deleteTest($markbookTables);
        }

        $externalPing = new \External_Ping();
        if ($externalPing->getVersion() > 67) {
            $mediaDb     = null;
            $mediaSchema = 'media';
        } else {
            $mediaDb     = 'SynergyOneMedia';
            $mediaSchema = 'dbo';
        }

        if (
            // Combine these two to avoid repeating tests.
            $this->configMapper->get('external_pastoral_upload') ||
            $this->configMapper->get('external_document_report_enabled')
        ) {
            $tablesForInsertTest = [];
            $tablesForSelectTest = [];
            $prefix              = $mediaDb ? ($mediaDb . '.') : '';

            // Pastoral upload tests.
            if ($this->configMapper->get(ConfigMapper::NAME_PASTORAL_EXTERNAL_UPLOAD)) {
                $tablesForInsertTest[] = $prefix . $mediaSchema . '.tDocuments';
                $tablesForInsertTest[] = $prefix . $mediaSchema . '.DocumentReferences';
                $tablesForInsertTest[] = $prefix . $mediaSchema . '.tDocumentReferences';
                $tablesForSelectTest[] = 'tDocuments';
                $tablesForSelectTest[] = 'DocumentReferences';
                $tablesForSelectTest[] = 'tDocumentReferences';
            }

            // External document reports.
            if ($this->configMapper->get('external_document_report_enabled')) {
                $tablesForSelectTest[] = 'StudentReportsArchiveLink';
                $tablesForSelectTest[] = 'StudentReportsArchive';
                $tablesForSelectTest[] = 'tDocuments';
                $tablesForSelectTest[] = 'DocumentReferences';
                $tablesForSelectTest[] = 'pvDocumentReferences';
            }

            $this->insertTest(array_unique($tablesForInsertTest));
            if (
                $this->configMapper->get(ConfigMapper::NAME_PASTORAL_EXTERNAL_UPLOAD)
                && $this->configMapper->get(ConfigMapper::NAME_PASTORAL_EXTERNAL_DELETE)
            ) {
                $this->deleteTest(array_unique($tablesForInsertTest));
            }
            $this->selectTest(
                array_unique($tablesForSelectTest),
                $mediaDb,
                $mediaSchema
            );
        }

        // Community portal password update.
        if ($this->configMapper->get('synergetic_password_reset')) {
            $this->selectTest(['CommunityLogins']);
            $this->updateTest(['CommunityLogins']);

            if ($this->configMapper->get('synergetic_portal_create_account')) {
                $this->insertTest(['CommunityLogins']);
                $this->insertTest(['CommunityLoginsPage']);
            }
        }

        $procedures = [
            'spgTimetableDay',
            'spsConfigUserDefaultYearSemester',
            'spsTimetable',
            'spiwkAttendances',
            'spswkAttendances',
            'sptwkAttendances',
            'spsTimetableDefinition',
            'spiExcursions',
            'spuExcursionDetails',
            'spiCommunityEventsCurrent',
        ];
        $this->executeTest($procedures);

        $functions = ['dbo.fnTimeStr'];
        $this->executeTest($functions);
    }

    private function schoolproTest(): void
    {
        $procedures = [
            'sbGetDayNumber',
            'sbGetEnrolledStudents',
            'sbGetGuardianAccounts',
            'sbGetParent',
            'sbGetPeriodNumber',
            'sbGetPortraitFile',
            'sbGetPortraitLastUpdated',
            'sbGetStaffAccounts',
            'sbGetStaffDayTimetable',
            'sbGetStaffSubjectList',
            'sbGetStudent',
            'sbGetStudents',
            'sbGetStudentAccounts',
            'sbGetStudentContact',
            'sbGetStudentSubjectList',
            'sbGetStudentDayTimetable',
            'sbGetStudentMedicalConditions',
            'sbGetTeacherName',
            'sbGetTimetableDefinition',
        ];
        $this->executeTest($procedures);
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
            "TimeTableTermCalendar",
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
        $list = [
            'V_Class',
            'V_Parent',
            'V_Staff',
            'V_Student',
            'V_TimetableSchedule',
            'V_TimetableStructure',
        ];

        $list = array_merge($list, $this->customUPSTest());
        $this->selectTest($list);
    }
}
