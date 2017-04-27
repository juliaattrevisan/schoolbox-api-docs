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
        // Make sure databases connected correctly

        if ($this->connector == null) {
            $this->log .= "ERROR: Unable to connect to database '{$this->connector->getDatabase()}' (Hostname: {$this->connector->getHostname()})\n";
        }

        /** @var IConnector $connector */
        foreach ($this->secondaryConnectors as $connector) {
            if ($connector == null) {
                $this->log .= "ERROR: Unable to connect to database '{$connector->getDatabase()}' (Hostname: {$connector->getHostname()})\n";
            }
        };

        $this->log .= str_pad(str_pad("METHOD", 15) . "DATABASE OBJECT", 50) . "TEST RESULT\n";
        $this->log .= "------------------------------------------------------------------------------------------------------------------\n";

        $testFunctionName = "{$this->externalType}Test";

        $this->$testFunctionName();

        return [
            'log' => trim($this->log) . "\n",
            'errors' => $this->errors,
            'externalType' => $this->externalType
        ];
    }

    /**
     * Test a query against each database object in the supplied array
     *
     * @param array $objects
     * @param string $query
     */
    private function selectTest($objects, $query = "SELECT TOP 1 * FROM :object")
    {
        sort($objects);

        $method = (explode(' ', trim($query)))[0];

        foreach ($objects as $object) {

            $this->log .= str_pad(str_pad($method, 15) . $object, 50);

            $sql = str_replace(':object', $object, $query);
            $statement = $this->connector->prepareQuery($sql);

            if ($statement->execute()) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "FAILED\n";
                $this->errors[] = "$sql\n{$statement->errorInfo()}";
            }
        }
    }

    /**
     * Test a list of database functions
     *
     * @param array $objects
     */
    private function functionTest($objects)
    {
        foreach ($objects as $name => $parameters) {

            $this->log .= str_pad(str_pad('FUNCTION', 15). $name, 50);

            $statement = $this->connector->prepareFunctionCall($name);

            if (is_array($parameters)) {
                $statement->bindInputs($parameters);
            }

            if ($statement->execute()) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "FAILED\n";
                $this->errors[] = "EXEC {$name}\n{$statement->errorInfo()}";
            }

            unset($outputValues);
        }
    }

    /**
     * Test procedure execution against each database object in the supplied array
     *
     * @param array $objects
     */
    private function procedureTest($objects)
    {
        sort($objects);

        $outputValues = [];

        foreach ($objects as $object) {

            $this->log .= str_pad(str_pad('EXECUTE', 15). $object["name"], 50);

            $statement = $this->connector->prepareStoredProcedure($object['name']);

            if (is_array($object['inputParameters'])) {
                $statement->bindInputs($object['inputParameters']);
            }

            if (is_array($object['outputParameters'])) {
                foreach ($object['outputParameters'] as $name => $parameter) {
                    $this->outputParameters[$name] = null;
                    $parameter['value'] = &$this->outputParameters[$name];
                }

                $statement->bindOutputs($object['outputParameters']);
            }

            if ($statement->execute()) {
                $this->log .= "OK\n";
            } else {
                $this->log .= "FAILED\n";

                $error = "EXECUTE {$object['name']}\n{$statement->errorInfo()}\n";

                $error .= "\n{$statement->getSql()}\n";

                foreach ($object['inputParameters'] as $key => $value) {

                    if ($value == null) {
                        $result = 'NULL';
                    } else {
                        $result = "'{$value}'";
                    }

                    $error .= "\n" . str_pad(str_pad("INPUT", 15) . "" . $key, 50) . str_pad(BaseStatement::getDataTypeName($value), 15) . $result;
                }

                if (is_array($object['outputParameters'])) {
                    foreach ($object['outputParameters'] as $parameterName => $optionArray) {
                        $error .= "\n" . str_pad(str_pad("OUTPUT", 15) . $parameterName, 50);

                        if ($outputValues[$object['name']][$parameterName] == null) {
                            $result = 'NULL';
                        } else {
                            $result = "'{$outputValues[$object['name']][$parameterName]}'";
                        }

                        $error .= str_pad(BaseStatement::getDataTypeName($optionArray['type']) . "(" . $optionArray['length'] . ")", 15) . $result;

                    }
                }

                $this->errors[] = $error;
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
            'db2inst1.schoolbox_classes',
            'db2inst1.schoolbox_parents',
            'db2inst1.schoolbox_report_periods',
            'db2inst1.schoolbox_studentclass',
            'db2inst1.schoolbox_students',
            'db2inst1.schoolbox_teacherclass',
            'db2inst1.schoolbox_parent_student',
            'db2inst1.schoolbox_staff',
            'db2inst1.schoolbox_timetable',
            'db2inst1.schoolbox_timetable_definition'
        ];

        $this->selectTest($tables, "SELECT * FROM :TABLE FETCH FIRST 1 ROWS ONLY");
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
            [
                'name' => 'spSelTimetable',
                'inputParameters' => [
                    'DATE' => date('Ymd'),
                    'TIMEPERIOD' => '',
                    'STUDKEY' => '',
                    'TEACHERCODE' => ''
                ]
            ]
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
            'Constituencies',
            'FileSemesters',
            'luBoarder',
            'luBuilding',
            'luCountry',
            'luDepartment',
            'luFileType',
            'luForm',
            'luHouse',
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
            'luDays',
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
            'StaffAssessmentMarkBook',
            'StudentAssessmentMarkBook',
            'luAbsenceReason',
            'luAbsenceType',
            'TimetableSubstitutes',
            'wkAttendances',
            'AttendanceMaster',
            'tAttendances'
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

        // Community Portal Password Update

        $this->selectTest(['CommunityLogins'], "UPDATE :object SET Password = '' WHERE 1 = 0");

        // Stored Procedures

        $procedures = [
            [
                'name' => 'spgTimetableDay',
                'inputParameters' => [
                    'TimetableGroup' => 'SEN',
                    'Date' => Alaress_Date_Format::now()->getDatabase()
                ],
                'outputParameters' => [
                    'CurrentDay' => [
                        'type' => PDO::PARAM_INT,
                        'length' => 11
                    ]
                ]
            ],
            [
                'name' => 'spsConfigUserDefaultYearSemester',
                'inputParameters' => [
                    'ID' => 1
                ]
            ],
            [
                'name' => 'spsTimetable',
                'inputParameters' => [
                    'FileYear' => Alaress_Date_Format::now()->getYear(),
                    'FileSemester' => Alaress_Date_Format::now()->getYearHalf(),
                    'ID' => 1,
                    'Date' => Alaress_Date_Format::now()->getDatabase(),
                    'IncludeStaffScheduleFlag' => 1,
                    'CheckAttendancesExistFlag' => 1,
                    'IncludeSparesFlag' => 0,
                    'IncludeRecessFlag' => 0
                ]
            ],
            [
                'name' => 'spiwkAttendances',
                'inputParameters' => [
                    'FileType' => 'A',
                    'FileYear' => 2010,
                    'FileSemester' => 1,
                    'ClassCampus' => 'S',
                    'ClassCode' => 'FREA1',
                    'AttendanceDate' => '2010-04-07',
                    'AttendancePeriod' => 1,
                    'AttendanceDayNumber' => 1,
                    'StaffID' => 1,
                    'AttendedFlag' => 1,
                    'TimeTableGroup' => 'SEN',
                    'SynWebFlag' => 1
                ],
                'outputParameters' => [
                    'wkAttendanceMasterSeq' => [
                        'type' => PDO::PARAM_INT,
                        'length' => 11
                    ]
                ]
            ],
            [
                'name' => 'spswkAttendances',
                'inputParameters' => [
                    'wkAttendanceMasterSeq' => 1,
                    'FillActualAbsenceColumnsFlag' => 1,
                    'CheckPossibleNonAttendFlag' => 1,
                    'CalculateTodaysAttendanceStringFlag' => 1
                ]
            ],
            [
                'name' => 'sptwkAttendances',
                'inputParameters' => [
                    'wkAttendanceMasterSeq' => 1,
                    'AttendanceSeq' => null,
                    'HoldFlag' => 0,
                    'ClassCancelledFlag' => 0,
                    'SystemProcessNumber' => 0,
                    'AllowMultiPeriodFlag' => 0,
                    'DebugPrintFlag' => 0,
                ],
                'outputParameters' => [
                    'AttendanceMasterSeq' => [
                        'type' => PDO::PARAM_INT,
                        'length' => 11
                    ],
                    'GroupCount' => [
                        'type' => PDO::PARAM_INT,
                        'length' => 11
                    ]
                ]
            ]
        ];

        $this->procedureTest($procedures);

        $functions = [
            'dbo.fnTimeStr' => [
                'ADateTime' => date('Y-m-d')
            ]
        ];

        $this->functionTest($functions);
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function schoolproTest()
    {
        /*
        $this->log .= "\nSchoolpro Permission Check - Stored Procedures\n";

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
        */
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
            "fc.TimetableSubject",
            "fc.TimetableSubjectChoice",
            "fc.Timetable",
            "fc.TimetableClass",
            "fc.YearLevelSub",
            "fc.YearLevelSubStudent",
            "fc.TimetableClassInstance",
            "fc.TimetableClassStaff",
            "fc.TimetableClassInstanceStaff",
            "fc.TimetableClassRoom",
            "fc.Room",
            "fc.TimetableClassInstanceRoom",
            "fc.TimetableDay",
            "fc.TimetablePeriod",
            "fc.TimetableDate",
            "fc.TimetableBlockInstance",
            "fc.TimetableBlockInstance tbi CROSS APPLY fc.PeriodExpand(tbi.TimetablePeriodID, tbi.Consecutive) tpe"
        ];

        $this->selectTest($tables);
    }
}
