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
                'args' => [
                    'currentstatus' => 'current'
                ]
            ],

            /* Emergency contacts
             * https://github.com/TheAlphaSchoolSystemPTYLTD/student-details/blob/master/getCommunicationRulesDetails.md
             */
            [
                'method' => 'getCommunicationRulesDetails',
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
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0'
                ]
            ],
            [
                'method' => 'getConfAttachment',
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
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalAsthmaManagement',
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalCondition',
                'args' => [
                    'studcode' => '123456',
                    'mcond_code' => 'AST'
                ]
            ],
            [
                'method' => 'getMedicalConditionAttachment',
                'args' => [
                    'studcode' => '123456',
                    'mcond_code' => 'AST',
                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
                ]
            ],
            [
                'method' => 'getMedicalConditions',
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalConfNote',
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                ]
            ],
            [
                'method' => 'getMedicalConfNoteAttachment',
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
                ]
            ],
            [
                'method' => 'getMedicalConfNotes',
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalGeneral',
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalIllness',
                'args' => [
                    'studcode' => '123456',
                    'ill_date' => '2019-04-12',
                    'ill_time' => '08:49:00.0',
                    'mcond_code' => 'ACC',
                ]
            ],
            [
                'method' => 'getMedicalImmunisation',
                'args' => [
                    'studcode' => '123456',
                    'imm_code' => 'TY',
                ]
            ],
            [
                'method' => 'getMedicalImmunisations',
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalNote',
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                ]
            ],
            [
                'method' => 'getMedicalNoteAttachment',
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
                ]
            ],
            [
                'method' => 'getMedicalNotes',
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalPractitioner',
                'args' => [
                    'studcode' => '123456',
                    'prac_num' => 9
                ]
            ],
            [
                'method' => 'getMedicalPractitioners',
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalSupplementaries',
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getMedicalSupplementary',
                'args' => [
                    'studcode' => '123456',
                    'msupp_desc' => 'DEN'
                ]
            ],
            [
                'method' => 'getNote',
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                ]
            ],
            [
                'method' => 'getNoteAttachment',
                'args' => [
                    'studcode' => '123456',
                    'note_cat' => 'GEN',
                    'note_date' => '2019-05-30 15:11:58.0',
                    'attach_id' => '2F8F9A77-E2B9-28D5-611EDE1D0A66D623'
                ]
            ],
            [
                'method' => 'getNotes',
                'args' => [
                    'studcode' => '123456',
                ]
            ],
            [
                'method' => 'getStudentUD',
                'args' => [
                    'code' => '123456',
                ]
            ],
            [
                'method' => 'getStudentUDArea',
                'args' => [
                    'code' => '123456',
                    'areacode' => 1
                ]
            ],
            [
                'method' => 'getStudentUDAreaSetup',
                'args' => [
                    'includelookups' => true
                ]
            ],
            [
                'method' => 'getStudentUDSetup',
                'args' => [
                    'includelookups' => true
                ]
            ],
            [
                'method' => 'getStudentsDetails',
                'args' => [
                    'currentstatus' => 'current'
                ]
            ],

            /* Stuff from IdM Identity Management API
             * https://github.com/TheAlphaSchoolSystemPTYLTD/IdM
             */
            [
                'method' => 'getAdmins',
                'args' => [
                    'user_code' => 'all',
                ]
            ],
            [
                'method' => 'getEmployees',
                'args' => [
                    'user_code' => 'all',
                ]
            ],
            [
                'method' => 'getParents',
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
                    'status' => ISpecification::STATUS_OK
                ];
            } else {
                $endpoints[] = [
                    'message' => "{$path['method']} ({$errorMessage})",
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
                'status' => ISpecification::STATUS_MISSING
            ];
        }

        foreach ($timetables as $timetable) {
            $endpoints[] = [
                'message' => '',
                'status' => ISpecification::STATUS_OK
            ];
        }

        return $endpoints;
    }
}
