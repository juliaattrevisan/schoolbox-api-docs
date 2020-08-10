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
    private $host;


    /**
     * @var string
     */
    private $dsn;


    /**
     * Specification constructor.
     *
     * @param string $host
     * @param string $dsn
     */
    public function __construct(string $host, string $dsn)
    {
        $this->host = $host;
        $this->dsn = $dsn;
    }


    /**
     * Get a list of nodes that did, or didn't, get configured properly
     *
     * @return array
     * @throws DomainException
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     * @throws InvalidArgumentException
     */
    public function check(): array
    {
        $modules = [
            DSNCodec::MODULE_LMS_INTEGRATION,
            DSNCodec::MODULE_STUDENT_DETAILS,
            DSNCodec::MODULE_IDENTITY_MANAGEMENT
        ];

        foreach ($modules as $module) {
            $api[$module] = new Api($this->host, $this->dsn, $module);
        }

        $paths = [

            /**
             * Full Diagnostic
             *
             * Tass is a coalition of API subsystems:
             *   LMS Integration, Student Details, Identity Management
             *
             * We need access to all three to complete the mission
             */

            /* LMS Integration Version 3
             * https://github.com/TheAlphaSchoolSystemPTYLTD/lms-integration
             */
            [
                'api' => DSNCodec::MODULE_LMS_INTEGRATION,
                'method' => 'getCampuses',
                'version' => 3,
                'args' => [
                    'codeonly' => 'false'
                ]
            ],
            [
                'api' => DSNCodec::MODULE_LMS_INTEGRATION,
                'method' => 'getStudentSubjects',
                'version' => 3,
                'args' => [
                    'lmsflag' => 'Y'
                ]
            ],
            [
                'api' => DSNCodec::MODULE_LMS_INTEGRATION,
                'method' => 'getStudentTimetable',
                'version' => 3,
                'args' => [
                    'student_code' => '12345'
                ]
            ],
            [
                'api' => DSNCodec::MODULE_LMS_INTEGRATION,
                'method' => 'getStudents',
                'version' => 3,
                'args' => [
                    'currentstatus' => 'current'
                ]
            ],
            [
                'api' => DSNCodec::MODULE_LMS_INTEGRATION,
                'method' => 'getTeacherSubjects',
                'version' => 3,
                'args' => [
                    'code' => 'all'
                ]
            ],
            [
                'api' => DSNCodec::MODULE_LMS_INTEGRATION,
                'method' => 'getTeacherTimetable',
                'version' => 3,
                'args' => [
                    'teacher_code' => 'all',
                    'year_num' => '10',
                    'semester' => '1',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_LMS_INTEGRATION,
                'method' => 'getTeachers',
                'version' => 3,
                'args' => [
                    'currentstatus' => 'current',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_LMS_INTEGRATION,
                'method' => 'getTimetableCalendar',
                'version' => 3,
                'args' => [
                    'tt_id' => '1',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_LMS_INTEGRATION,
                'method' => 'getTimetables',
                'version' => 3,
                'args' => [
                    'year_num' => '10',
                    'period' => '1'
                ]
            ],

            /* Student Details Version 3
             * https://github.com/TheAlphaSchoolSystemPTYLTD/student-details
             */

            [
                /* Emergency contacts
                 */
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
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
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getCommunicationRulesDetails',
                'version' => 3,
                'args' => [
                    'currentstatus' => 'current',
                    'commtype' => 'all'
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getConfNote',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0'
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getConfAttachment',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'

                    /* lol, mcond_code is a sample param that doesn't match their own spec
                     * mcond_code is a Medical Conditions code
                     */
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getConfNotes',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalAsthmaManagement',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalCondition',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'mcond_code' => 'AST'
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalConditionAttachment',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'mcond_code' => 'AST',
                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalConditions',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalConfNote',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalConfNoteAttachment',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalConfNotes',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalGeneral',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalIllness',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'ill_date' => '2019-04-12',
                    'ill_time' => '08:49:00.0',
                    'mcond_code' => 'ACC',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalImmunisation',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'imm_code' => 'TY',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalImmunisations',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalNote',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalNoteAttachment',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalNotes',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalPractitioner',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'prac_num' => 9
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalPractitioners',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalSupplementaries',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getMedicalSupplementary',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'msupp_desc' => 'DEN'
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getNote',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getNoteAttachment',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getNotes',
                'version' => 3,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getStudentUD',
                'version' => 3,
                'args' => [
                    'code' => '123456',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getStudentUDArea',
                'version' => 3,
                'args' => [
                    'code' => '123456',
                    'areacode' => 1
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getStudentUDAreaSetup',
                'version' => 3,
                'args' => [
                    'includelookups' => true
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getStudentUDSetup',
                'version' => 3,
                'args' => [
                    'includelookups' => true
                ]
            ],
            [
                'api' => DSNCodec::MODULE_STUDENT_DETAILS,
                'method' => 'getStudentsDetails',
                'version' => 3,
                'args' => [
                    'currentstatus' => 'current'
                ]
            ],

            /* IdM Identity Management Version 3
             * https://github.com/TheAlphaSchoolSystemPTYLTD/IdM
             */
            [
                'api' => DSNCodec::MODULE_IDENTITY_MANAGEMENT,
                'method' => 'getAdmins',
                'version' => 3,
                'args' => [
                    'user_code' => 'all',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_IDENTITY_MANAGEMENT,
                'method' => 'getEmployees',
                'version' => 3,
                'args' => [
                    'user_code' => 'all',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_IDENTITY_MANAGEMENT,
                'method' => 'getParents',
                'version' => 3,
                'args' => [
                    'user_code' => 'all',
                ]
            ],
            [
                'api' => DSNCodec::MODULE_IDENTITY_MANAGEMENT,
                'method' => 'getStudents',
                'version' => 3,
                'args' => [
                    'user_code' => 'all',
                ]
            ],
        ];

        $endpoints = [];

        foreach ($paths as $path) {
            $errorMessage = '';
            $message = "{$path['api']}: {$path['method']}";

            try {
                $result = (bool)$api[$path['api']]->retrieveDocument($path['method'], $path['args']);
            } catch (RuntimeException $e) {
                $errorMessage = $e->getMessage();
                $result = false;
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
