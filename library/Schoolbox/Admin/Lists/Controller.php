<?php
/**
 * Lists admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Lists;

use Alaress\Web\Controller\Base;
use Schoolbox\User;
use Schoolbox\Resource\Storage;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\News\Topic;
use Schoolbox\News\TopicMapper;
use Schoolbox\Assessment\Component\SubmissionBox\WorkType;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }

    /**
     * Show options
     *
     * @param string $tab
     * @param string $table
     */
    public function getIndex($tab = '', $table = 'groups')
    {
        $tables = [
            'roles' => [
                'title'             => 'Roles',
                'prototype'         => Role::class,
                'mapper'            => RoleMapper::class,
                'fields'            => [
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 9,
                    ],
                    'type' => [
                        'caption' => 'Role Type',
                        'lookup' => [
                            ['key' => 'guest', 'value' => 'Guest'],
                            ['key' => 'staff', 'value' => 'Staff'],
                            ['key' => 'student', 'value' => 'Student'],
                            ['key' => 'parent', 'value' => 'Parent'],
                        ],
                        'width'     => 1,
                    ],
                    'canChangePassword' => [
                        'caption' => 'Allow Password Change',
                        'lookup' => [
                            ['key' => 1, 'value' => 'Yes'],
                            ['key' => 0, 'value' => 'No'],
                        ],
                        'width'     => 2,
                    ]
                ]
            ],
            'house' => [
                'title'             => 'Houses',
                'prototype'         => \House::class,
                'mapper'            => \HouseMapper::class,
                'fields'            => [
                    'house'         => [
                        'caption'   => 'Name',
                        'width'     => 12,
                    ]
                ]
            ],
            'luResidentialHouse'    => [
                'title'             => 'Residential Houses',
                'prototype'         => \HouseResidential::class,
                'mapper'            => \HouseResidentialMapper::class,
                'fields'            => [
                    'house'         => [
                        'caption'   => 'Name',
                        'width'     => 12,
                    ]
                ]
            ],
            'luCampus'    => [
                'title'             => 'Campus',
                'prototype'         => Campus::class,
                'mapper'            => CampusMapper::class,
                'fields'            => [
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 8,
                    ],
                    'extTimetableCode' => [
                        'caption'   => 'External Timetable Code',
                        'width'     => 2,
                    ],
                    'timetableDay' => [
                        'caption'   => 'Timetable Days',
                        'width'     => 2,
                    ]
                ]
            ],
            'luEventType'    => [
                'title'             => 'Event Types',
                'prototype'         => EventType::class,
                'mapper'            => EventTypeMapper::class,
                'fields'            => [
                    'type' => [
                        'caption'   => 'Event Type',
                        'width'     => 10,
                    ],
                    'color' => [
                        'caption'   => 'Event Colour',
                        'width'     => 2,
                    ]
                ]
            ],
            'newsTopic'    => [
                'title'             => 'News Topics',
                'prototype'         => Topic::class,
                'mapper'            => TopicMapper::class,
                'fields'            => [
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 11,
                    ],
                    'sequence' => [
                        'caption'   => 'Sequence',
                        'width'     => 1,
                    ],
                ]
            ],
            'lu_submission_type'    => [
                'title'             => 'Submission Types',
                'prototype'         => WorkType::class,
                'mapper'            => WorkTypeMapper::class,
                'fields'            => [
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 12,
                    ],
                ]
            ],
            'submission_errors'     => [
                'title'             => 'Submission States',
                'prototype'         => \DueWork_Error::class,
                'mapper'            => \DueWork_ErrorMapper::class,
                'fields'            => [
                    'code'          => [
                        'caption'   => 'Code',
                        'width'     => 1,
                    ],
                    'displayAs'     => [
                        'caption'   => 'Display Name',
                        'width'     => 7,
                    ],
                    'normaliseAs'   => [
                        'caption'   => 'Normalised Mark',
                        'nullable'  => true,
                        'width'     => 2,
                    ],
                    'isDefaultForNewSubmissions' => [
                        'getter'    => 'isIsDefaultForNewSubmissions',
                        'caption'   => 'Default for new submissions?',
                        'radio'     => '1',
                        'width'     => 2,
                    ]
                ]
            ],
            'component_definition'  => [
                'title'             => 'Components',
                'prototype'         => \Component_Definition::class,
                'mapper'            => \Component_DefinitionMapper::class,
                'fields'            => [
                    'module'        => [
                        'caption'   => 'Module',
                        'readonly'  => true,
                        'width'     => 1,
                    ],
                    'name'          => [
                        'caption'   => 'Name',
                        'readonly'  => true,
                        'width'     => 2,
                    ],
                    'description'   => [
                        'caption'   => 'Description',
                        'readonly'  => true,
                        'width'     => 8,
                    ],
                    'visible' => [
                        'caption'   => 'visible',
                        'lookup' => [
                            ['key' => 'true', 'value' => 'Yes'],
                            ['key' => 'false', 'value' => 'No'],
                        ],
                        'width'     => 1,
                    ]
                ]
            ],
            'lu_timetable_colour'   => [
                'title'             => 'Timetable Colours',
                'prototype'         => \Timetable_Colour::class,
                'mapper'            => \Timetable_ColourMapper::class,
                'sortable'          => 'order',
                'fields'            => [
                    'id'            => [
                        'getter'    => 'getTimetableColourId',
                        'hidden'    => true,
                    ],
                    'hex_colour'    => [
                        'getter'    => 'getHexColour',
                        'caption'   => 'Colour',
                    ],
                ]
            ]


        ];

        /** @var \Schoolbox\Mapper $mapper */
        $mapper = new $tables[$table]['mapper'];
        $prototype = new $tables[$table]['prototype'];

        $reflect = new \ReflectionClass($prototype);
        $properties   = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);

        $records = [];

        // Go through every row in the current lookup table

        $rows = $mapper->findAll();

        foreach ($rows as $row) {

            // Go through every property available

            foreach ($properties as $prop) {

                // We only want to display fields that are listed in the 'fields' array

                if (isset($tables[$table]['fields'][$prop->getName()])) {

                    // And they have to have a getId() column (or a manually defined getter function for the column)

                    if ($reflect->hasMethod('getId') || isset($tables[$table]['fields']['id']['getter']))
                    {
                        // And only if there is a mapper function to retrieve its value

                        if (isset($tables[$table]['fields'][$prop->getName()]['getter']))
                            $getter = $tables[$table]['fields'][$prop->getName()]['getter'];
                        else
                            $getter = "get" . ucfirst($prop->getName());

                        if ($reflect->hasMethod($getter)) {

                            if (isset($tables[$table]['fields']['id']['getter'])) {
                                $idGetter = $tables[$table]['fields']['id']['getter'];
                            } else {
                                $idGetter = 'getId';
                            }

                            $id = $row->$idGetter();

                            if (!isset($records[$id]))
                                $records[$id] = [];

                            $records[$id][$prop->getName()] = $row->$getter();
                        }
                    }
                }
            }
        }


        /*$tables = [
            'groups' => [
                'title' => 'Roles',
                'index' => 'id',
                'fields' => array(
                    'id'         => array('readonly' => '1'),
                    'name'       => array('caption' => 'Name'),
                    'group_type' => array('caption' => 'Role Type'),
                    'can_change_password' => array('caption' => 'Allow Password Change'),
                ),
            ],
            'luResidentialHouse' => array(
                'title' => 'Residential Houses',
                'index' => 'id'
            ),
            'luCampus' => array(
                'title' => 'Campus',
                'index' => 'id',
                'fields' => array(
                    'id' => array('readonly' => '1'),
                    'name' => array('caption' => 'Name'),
                    'ext_timetable_code' => array('caption'=>'External Timetable Code'),
                    'timetable_day' => array('caption'=>'Timetable Days'),
                )
            ),
            'luEventType' => array(
                'title' => 'Event Types',
                'index' => 'eventTypeID'
            ),
            'file_type' => array(
                'title' => 'Assessment Types',
                'index' => 'id'
            ),
            'newsTopic' => array(
                'title' => 'News Topics',
                'index' => 'newsTopicID'
            ),
            'lu_submission_type' => array(
                'title' => 'Submission Types',
                'index' => 'id'
            ),
            'submission_errors' => array(
                'title'  => 'Submission States',
                'index'  => 'id',
                'fields' => array(
                    'id' => array(
                        'readonly' => '1'
                    ),
                    'code' => array(
                        'caption' => 'Code',
                    ),
                    'display_as' => array(
                        'caption' => 'Display Name',
                    ),
                    'normalise_as' => array(
                        'caption' => 'Normalised Mark',
                        'nullable' => true,
                    ),
                    'is_default_for_new_submissions' => array(
                        'caption' => 'Default for new submissions?',
                    )
                ),
            ),
            'component_definition' => array(
                'title' => 'Components',
                'index' => 'id',
                'create' => false,
                'fields' => array(
                    'id' => array(
                        'readonly' => '1'
                    ),
                    'module' => array(
                        'caption' => 'Module',
                        'readonly' => '1'
                    ),
                    'name' => array(
                        'caption' => 'Component Name',
                        'readonly' => '1'
                    ),
                    'description' => array(
                        'caption' => 'Description',
                        'readonly' => '1'
                    ),
                    'visible' => array(
                        'caption' => 'Visible',
                        'readonly' => '0'
                    ),
                ),
            ),
            'lu_timetable_colour' => array(
                'title' => 'Timetable Colours',
                'index' => 'timetable_colour_id'
            )
        ];*/

        // Stick a name column in those with no fields defined

        foreach ($tables as $tableCurrent) {
            if (!isset($tableCurrent['fields'])) {
                $tableCurrent['fields'] = [];
                $tableCurrent['fields']['name'] = [
                    'caption' => 'Name'
                ];
            }
        }

        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminLists/index.twig',
                'data'     => array(
                    'pageCurrent' => basename($this->getRequest()->query->get('page')),
                    'tab' => $tab != '' ? $tab : 'course',
                    'tables' => $tables,
                    'table' => $table,
                    'records' => $records,
                ),
            )
        );
    }
}
