<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use RuntimeException;
use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;

/**
 * Class Specification
 *
 * This explores an xml output to check whether certain nodes exist
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint
 */
class Specification implements ISpecification
{

    /**
     * @var IApi
     */
    private $api;


    /**
     * Specification constructor.
     *
     * @param IApi $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;
    }


    /**
     * Get a list of nodes that did, or didn't, get configured properly
     *
     * @return array
     */
    public function check(): array
    {
        $paths = [

            /**
             * Student Profile nodes
             */
            [
                'method' => 'getStudents',
                'version' => 3,
                'args' => [
                    'currentstatus' => 'current'
                ]
            ],


            /* Emergency contacts
             * https://github.com/TheAlphaSchoolSystemPTYLTD/student-details/blob/master/getCommunicationRulesDetails.md
             */
            [
                'method' => 'getCommunicationRulesDetails',
                'version' => 2,
                'args' => [
                    'currentstatus' => 'current',
                    'commtype' => 'ec'
                ]
            ],


            /**
             * Parent Profile nodes
             */

            /* Parents come out in the m_* and f_* nodes
             * https://github.com/TheAlphaSchoolSystemPTYLTD/student-details/blob/master/getCommunicationRulesDetails.md
             */
            [
                'method' => 'getCommunicationRulesDetails',
                'version' => 2,
                'args' => [
                    'currentstatus' => 'current',
                    'commtype' => 'all'
                ]
            ],


            /**
             * Staff Profile nodes
             */


            /**
             * Timetable nodes
             */


            /**
             * Full Diagnostic
             * Tass doesn't seem to have implemented a lot of things, let's scout ahead
             */
            [
                'method' => 'getConfNote',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0'
                ]
            ],
            [
                'method' => 'getConfAttachment',
                'version' => 2,
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
                'method' => 'getConfNotes',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalAsthmaManagement',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalCondition',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                    'mcond_code' => 'AST'
                ]
            ],
            [
                'method' => 'getMedicalConditionAttachment',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                    'mcond_code' => 'AST',
                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
                ]
            ],
            [
                'method' => 'getMedicalConditions',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalConfNote',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                ]
            ],
            [
                'method' => 'getMedicalConfNoteAttachment',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
                ]
            ],
            [
                'method' => 'getMedicalConfNotes',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalGeneral',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalIllness',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                    'ill_date' => '2019-04-12',
                    'ill_time' => '08:49:00.0',
                    'mcond_code' => 'ACC',
                ]
            ],
            [
                'method' => 'getMedicalImmunisation',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                    'imm_code' => 'TY',
                ]
            ],
            [
                'method' => 'getMedicalImmunisations',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalNote',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                ]
            ],
            [
                'method' => 'getMedicalNoteAttachment',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
                ]
            ],
            [
                'method' => 'getMedicalNotes',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalPractitioner',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                    'prac_num' => 9
                ]
            ],
            [
                'method' => 'getMedicalPractitioners',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalSupplementaries',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalSupplementary',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                    'msupp_desc' => 'DEN'
                ]
            ],
            [
                'method' => 'getNote',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                ]
            ],
            [
                'method' => 'getNoteAttachment',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
                ]
            ],
            [
                'method' => 'getNotes',
                'version' => 2,
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getStudentUD',
                'version' => 2,
                'args' => [
                    'code' => '123456',
                ]
            ],
            [
                'method' => 'getStudentUDArea',
                'version' => 2,
                'args' => [
                    'code' => '123456',
                    'areacode' => 1
                ]
            ],
            [
                'method' => 'getStudentUDAreaSetup',
                'version' => 2,
                'args' => [
                    'includelookups' => true
                ]
            ],
            [
                'method' => 'getStudentUDSetup',
                'version' => 2,
                'args' => [
                    'includelookups' => true
                ]
            ],
            [
                'method' => 'getStudentsDetails',
                'version' => 2,
                'args' => [
                    'currentstatus' => 'current'
                ]
            ],

            /* Stuff from IdM Identity Management API
             * https://github.com/TheAlphaSchoolSystemPTYLTD/IdM
             */
            [
                'method' => 'getAdmins',
                'version' => 2,
                'args' => [
                    'user_code' => 'all',
                ]
            ],
            [
                'method' => 'getEmployees',
                'version' => 2,
                'args' => [
                    'user_code' => 'all',
                ]
            ],
            [
                'method' => 'getParents',
                'version' => 2,
                'args' => [
                    'user_code' => 'all',
                ]
            ],
        ];

        $endpoints = [];

        foreach ($paths as $path) {
            $errorMessage = '';

            try {
                $result = (bool)$this->api->retrieveDocument($path['method'], $path['args']);
            } catch (RuntimeException $e) {
                $errorMessage = $e->getMessage();
                $result = false;
            }

            if ($result) {
                $endpoints[] = [
                    'message' => $path['method'],
                    'reason' => '',
                    'status' => ISpecification::STATUS_OK
                ];
            } else {
                $endpoints[] = [
                    'message' => $path['method'],
                    'reason' => "v{$path['version']} {$errorMessage}",
                    'status' => ISpecification::STATUS_MISSING
                ];
            }
        }

        /* Get a list of all the timetables to process
         */
        $timetables = [];

        /* If there aren't any timetables, log it and exit
         */
        if (!\count($timetables)) {
            $endpoints[] = [
                'message' => 'No timetables detected.',
                'reason' => '',
                'status' => ISpecification::STATUS_MISSING
            ];
        }

        foreach ($timetables as $timetable) {
            $endpoints[] = [
                'message' => '',
                'reason' => '',
                'status' => ISpecification::STATUS_OK
            ];
        }

        return $endpoints;
    }
}
