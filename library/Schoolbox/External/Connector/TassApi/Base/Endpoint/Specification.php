<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;

/**
 * Class Specification
 *
 * This explores an api output to check whether certain nodes exist
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint
 */
class Specification implements ISpecification
{

    /**
     * @var string
     */
    private string $host;


    /**
     * @var string
     */
    private string $dsn;


    /**
     * @var bool
     */
    private bool $includePhoto;


    /**
     * Specification constructor.
     *
     * @param string $host
     * @param string $dsn
     * @param bool   $includePhoto
     */
    public function __construct(string $host, string $dsn, bool $includePhoto)
    {
        $this->host = $host;
        $this->dsn = $dsn;
        $this->includePhoto = $includePhoto;
    }


    /**
     * Get a list of nodes that did, or didn't, get configured properly
     *
     * @return array
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     * @throws \JsonException
     */
    public function check(): array
    {
        $modules = [
            Codec\DSN::MODULE_LMS_INTEGRATION,
            Codec\DSN::MODULE_STUDENT_DETAILS,
            Codec\DSN::MODULE_IDENTITY_MANAGEMENT,
            Codec\DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES,
            Codec\DSN::MODULE_EMPLOYEE_HR,
        ];

        foreach ($modules as $module) {
            try {
                $api[$module] = new Api($this->host, $this->dsn, $module);
            } catch (\DomainException $e) {
                /* noop
                 * occurs when a module doesn't exist but you tried to access its methods anyway
                 * such as Student Calendar And Notices (an optional module)
                 */
            }
        }

        $paths = [

            /**
             * Full Diagnostic
             *
             * Tass is a coalition of API subsystems:
             *   LMS Integration, Student Details, Identity Management, School Calendar And Notices
             *
             * We need access to the first three to complete the mission
             * and the fourth is optional
             */

            /* LMS Integration Version 3
             * https://github.com/TheAlphaSchoolSystemPTYLTD/lms-integration
             */
//            [
//                'api' => DSNCodec::MODULE_LMS_INTEGRATION,
//                'method' => 'getCampuses',
//                'version' => 3,
//                'args' => [
//                    'codeonly' => 'false'
//                ]
//            ],
            [
                'api' => Codec\DSN::MODULE_LMS_INTEGRATION,
                'method' => 'getStudentSubjects',
                'version' => 3,
                'args' => [
                    'lmsflag' => 'Y',
                    'code'    => 'all',
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_LMS_INTEGRATION,
                'method' => 'getStudentTimetable',
                'version' => 3,
                'args' => [
                    'student_code' => '12345'
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_LMS_INTEGRATION,
                'method' => 'getStudents',
                'version' => 3,
                'args' => [
                    'currentstatus' => 'current',
                    'includephoto'  => $this->includePhoto,
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_LMS_INTEGRATION,
                'method' => 'getTeacherSubjects',
                'version' => 3,
                'args' => [
                    'code' => 'all'
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_LMS_INTEGRATION,
                'method' => 'getTeacherTimetable',
                'version' => 3,
                'args' => [
                    'teacher_code' => 'all',
                    'year_num' => '10',
                    'semester' => '1',
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_LMS_INTEGRATION,
                'method' => 'getTeachers',
                'version' => 3,
                'args' => [
                    'currentstatus' => 'current',
                    'includephoto'  => $this->includePhoto,
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_LMS_INTEGRATION,
                'method' => 'getTimetableCalendar',
                'version' => 3,
                'args' => [
                    'tt_id' => '1',
                ]
            ],
//            [
//                'api' => Codec\DSN::MODULE_LMS_INTEGRATION,
//                'method' => 'getTimetables',
//                'version' => 3,
//                'args' => [
//                    'year_num' => '10',
//                    'period' => '1'
//                ]
//            ],

            /* Student Details Version 3
             * https://github.com/TheAlphaSchoolSystemPTYLTD/student-details
             */

            [
                /* Emergency contacts
                 */
                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
                'method' => 'getCommunicationRulesDetails',
                'version' => 3,
                'args' => [
                    'currentstatus' => 'current',
                    'commtype' => 'ec'
                ]
            ],
            [
                /* Parents come out in the m_* and f_* nodes
                 */
                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
                'method' => 'getCommunicationRulesDetails',
                'version' => 3,
                'args' => [
                    'currentstatus' => 'current',
                    'commtype' => 'all'
                ]
            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getConfNote',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'note_cat' => 'GEN',
//                    'note_date' => '2019-05-30 15:11:58.0'
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getConfAttachment',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'note_cat' => 'GEN',
//                    'note_date' => '2019-05-30 15:11:58.0',
//                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
//
//                    /* lol, mcond_code is a sample param that doesn't match their own spec
//                     * mcond_code is a Medical Conditions code
//                     */
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getConfNotes',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalAsthmaManagement',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalCondition',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'mcond_code' => 'AST'
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalConditionAttachment',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'mcond_code' => 'AST',
//                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
//                ]
//            ],
            [
                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalConditions',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalConfNote',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'note_cat' => 'GEN',
//                    'note_date' => '2019-05-30 15:11:58.0',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalConfNoteAttachment',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'note_cat' => 'GEN',
//                    'note_date' => '2019-05-30 15:11:58.0',
//                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalConfNotes',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalGeneral',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalIllness',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'ill_date' => '2019-04-12',
//                    'ill_time' => '08:49:00.0',
//                    'mcond_code' => 'ACC',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalImmunisation',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'imm_code' => 'TY',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalImmunisations',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalNote',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'note_cat' => 'GEN',
//                    'note_date' => '2019-05-30 15:11:58.0',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalNoteAttachment',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'note_cat' => 'GEN',
//                    'note_date' => '2019-05-30 15:11:58.0',
//                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalNotes',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalPractitioner',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'prac_num' => 9
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalPractitioners',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalSupplementaries',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getMedicalSupplementary',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'msupp_desc' => 'DEN'
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getNote',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'note_cat' => 'GEN',
//                    'note_date' => '2019-05-30 15:11:58.0',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getNoteAttachment',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                    'note_cat' => 'GEN',
//                    'note_date' => '2019-05-30 15:11:58.0',
//                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getNotes',
//                'version' => 3,
//                'args' => [
//                    'studcode' => '123456',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getStudentUD',
//                'version' => 3,
//                'args' => [
//                    'code' => '123456',
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getStudentUDArea',
//                'version' => 3,
//                'args' => [
//                    'code' => '123456',
//                    'areacode' => 1
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getStudentUDAreaSetup',
//                'version' => 3,
//                'args' => [
//                    'includelookups' => true
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getStudentUDSetup',
//                'version' => 3,
//                'args' => [
//                    'includelookups' => true
//                ]
//            ],
//            [
//                'api' => Codec\DSN::MODULE_STUDENT_DETAILS,
//                'method' => 'getStudentsDetails',
//                'version' => 3,
//                'args' => [
//                    'currentstatus' => 'current'
//                ]
//            ],

            /* IdM Identity Management Version 3
             * https://github.com/TheAlphaSchoolSystemPTYLTD/IdM
             */
//            [
//                'api' => Codec\DSN::MODULE_IDENTITY_MANAGEMENT,
//                'method' => 'getAdmins',
//                'version' => 3,
//                'args' => [
//                    'user_code' => 'all',
//                ]
//            ],
            [
                'api' => Codec\DSN::MODULE_IDENTITY_MANAGEMENT,
                'method' => 'getEmployees',
                'version' => 3,
                'args' => [
                    'user_code' => 'all',
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_IDENTITY_MANAGEMENT,
                'method' => 'getParents',
                'version' => 3,
                'args' => [
                    'user_code' => 'all',
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_IDENTITY_MANAGEMENT,
                'method' => 'getStudents',
                'version' => 3,
                'args' => [
                    'user_code' => 'all',
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_EMPLOYEE_HR,
                'method' => 'getEmployeesDetails',
                'version' => 2,
                'args' => [
                    'date' => date('d/m/Y'),
                ]
            ],

            /* School Calendar and Notices is only on version 2, not 3
             */
            [
                'api' => Codec\DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES,
                'method' => 'getParentCalendar',
                'version' => 2,
                'args' => [
                    'start_date' => \Alaress_Date_Format::now()->addDays(-1)->getUsingFormat('d/m/Y'),
                    'end_date' => \Alaress_Date_Format::now()->addDays(+1)->getUsingFormat('d/m/Y'),
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES,
                'method' => 'getParentNotices',
                'version' => 2,
                'args' => [
                    'start_date' => \Alaress_Date_Format::now()->addDays(-1)->getUsingFormat('d/m/Y'),
                    'end_date' => \Alaress_Date_Format::now()->addDays(+1)->getUsingFormat('d/m/Y'),
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES,
                'method' => 'getStudentCalendar',
                'version' => 2,
                'args' => [
                    'start_date' => \Alaress_Date_Format::now()->addDays(-1)->getUsingFormat('d/m/Y'),
                    'end_date' => \Alaress_Date_Format::now()->addDays(+1)->getUsingFormat('d/m/Y'),
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES,
                'method' => 'getStudentNotices',
                'version' => 2,
                'args' => [
                    'start_date' => \Alaress_Date_Format::now()->addDays(-1)->getUsingFormat('d/m/Y'),
                    'end_date' => \Alaress_Date_Format::now()->addDays(+1)->getUsingFormat('d/m/Y'),
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES,
                'method' => 'getTeacherCalendar',
                'version' => 2,
                'args' => [
                    'start_date' => \Alaress_Date_Format::now()->addDays(-1)->getUsingFormat('d/m/Y'),
                    'end_date' => \Alaress_Date_Format::now()->addDays(+1)->getUsingFormat('d/m/Y'),
                ]
            ],
            [
                'api' => Codec\DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES,
                'method' => 'getTeacherNotices',
                'version' => 2,
                'args' => [
                    'start_date' => \Alaress_Date_Format::now()->addDays(-1)->getUsingFormat('d/m/Y'),
                    'end_date' => \Alaress_Date_Format::now()->addDays(+1)->getUsingFormat('d/m/Y'),
                ]
            ],
        ];

        $endpoints = [];

        foreach ($paths as $path) {
            $errorMessage = '';
            $message = "{$path['api']}: {$path['method']}";

            /* The DSN configuration wasn't provided for the product
             * that we're currently testing
             */
            if (!isset($api[$path['api']])) {
                $errorMessage = sprintf(
                    'Missing DSN configuration for %s',
                    $path['api']
                );

                $result = false;
            } else {
                try {
                    $result = (bool)$api[$path['api']]->retrieveDocument($path['method'], $path['args'], $path['version']);
                } catch (RuntimeException $e) {
                    $errorMessage = $e->getMessage();
                    $result = false;
                }
            }

            if ($result) {
                $endpoints[] = [
                    'message' => $message,
                    'reason' => '',
                    'status' => ISpecification::STATUS_OK
                ];
            } else {
                $endpoints[] = [
                    'message' => $message,
                    'reason' => "v{$path['version']} {$errorMessage}",
                    'status' => ISpecification::STATUS_MISSING
                ];
            }
        }

        return $endpoints;
    }
}
