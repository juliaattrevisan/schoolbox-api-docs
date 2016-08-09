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
use Schoolbox\RoleMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\News\TopicMapper;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;
use Schoolbox\Pastoral\Severity\Mapper as SeverityMapper;
use Schoolbox\Pastoral\Type\Mapper as TypeMapper;
use Schoolbox\Pastoral\Category\Mapper as CategoryMapper;

class Controller extends Base
{
    /**
     * Converts "strings_like_this" to "StringsLikeThis"
     *
     * @param $string
     * @param bool $capitalizeFirstCharacter
     * @return mixed
     */
    private function underscoreToCamelCase($string, $capitalizeFirstCharacter = true)
    {
        $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));

        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        } else {
            $str[0] = strtoupper($str[0]);
        }

        return $str;
    }

    /**
     * @api /adminv2/lists/save
     * @level 160
     */
    public function postSave()
    {
        $tableKey        = $this->getRequest()->post['table'];
        $tableData       = $this->getTableData();
        $mapper_name     = $tableData[$tableKey]['mapper'];
        $sortable_column = $this->getRequest()->post["sortable_column"];

        /** @var \Schoolbox\Mapper $mapper */
        $mapper = new $mapper_name;
        $model_name = $mapper->getModel();

        $ids    = [];
        $values = [];
        $fixed  = [];
        $nulls  = [];
        $radios = [];

        // Get the data into some sort of usable format

        foreach ($this->getRequest()->post as $field => $value) {

            if (substr($field, 0, 6) == "value-") {
                $values[substr($field, 6)] = $value;
            } else if (substr($field, 0, 6) == "fixed-") {
                $fixed[substr($field, 6)] = $value;
            } else if (substr($field, 0, 5) == "null-") {
                $nulls[substr($field, 5)] = $value;
            } else if (substr($field, 0, 6) == "radio-") {
                $radios[substr($field, 6)] = $value;
            } else if ($field == "id") {
                $ids = $value;
            }
        }

        foreach ($ids as $currentIndex => $id)
        {
            // Negative ID numbers indicate new records

            if ($id > 0) {
                // Lookup existing record
                $model = $mapper->findById($id);
            } else {
                // Create new record
                $model = new $model_name;
                foreach ($fixed as $field => $value) {
                    $setter = "set" . $this->underscoreToCamelCase($field);
                    $model->$setter($value);
                }
            }

            foreach ($values as $field => $value)
            {
                // Update values

                $setter = "set" . $this->underscoreToCamelCase($field);
                $model->$setter($value[$currentIndex]);

                // Override nullable fields where appropriate

                if (isset($nulls[$field])) {
                    foreach ($nulls[$field] as $nullableField => $index) {
                        if ($currentIndex == $index) {
                            $model->$setter(null);
                        }
                    }
                }
            }

            // Process any radio buttons for this list

            foreach ($radios as $field => $index)
            {
                $setter = "set" . $this->underscoreToCamelCase($field);

                // Radio buttons can only be on for one row, so set that row to true and all others to false

                if ($id == $ids[$index]) {
                    $model->$setter(1);
                } else {
                    $model->$setter(0);
                }
            }

            if (trim($sortable_column) != "") {
                $setter = "set" . $this->underscoreToCamelCase($sortable_column);
                $model->$setter($currentIndex);
            }

            // Save the changes

            $mapper->save($model);
        }

        $this->getResponse()->redirect->afterPost('/adminv2/lists/' . $this->getRequest()->post["table"]);
    }


    /**
     * Gets all table settings
     *
     * @return array
     */
    private function getTableData()
    {
        // This array defines the tables that can be updated
        // Note that they are NOT indexed by actual table name, but by an arbitrary key.

        return [
            'roles' => [
                'title'             => 'Roles',                                 // Title of the tab
                'mapper'            => RoleMapper::class,                       // Mapper for the list item
                //'findAllMethod'     => 'findAllIncludeHidden',                // By default it uses the findAll() method of the mapper- however you can override this. Necessary for 'components' page where findAll is overridden to not return items with 'visible = false'
                'fixed'             => [                                        // This is a list of fixed values added to new items, useful if there are hidden items that need default values
                    'digestSchedule' => '0'                                     // (e.g. Digest schedule cannot be null, so is hardcoded to 0 for new records)
                ],
                'fields'            => [                                        // This is a list of all of the updateable fields
                    'name'          => [
                        'caption'   => 'Name',                                  // Friendly caption that is displayed
                        'width'     => 6,                                       // Width of the column on anything bigger than 'small'
                    ],
                    'type' => [
                        //'radio'     => '1'                                    // If defined the column will be a radio button shared by every item
                        //'nullable'  => '1'                                    // If defined the column will have a checkbox that can be ticked to select NULL as a value
                        //'getter'    => 'getTimetableColourId',                // Can be used to override getter if it has some weird name
                        'caption'   => 'Role Type',
                        'lookup'    => [                                        // If defined the column will appear as a select box
                            ['key' => 'guest', 'value' => 'Guest'],
                            ['key' => 'staff', 'value' => 'Staff'],
                            ['key' => 'student', 'value' => 'Student'],
                            ['key' => 'parent', 'value' => 'Parent'],
                        ],
                        'width'     => 3,
                    ],
                    'canChangePassword' => [
                        'caption'   => 'Allow Password Change',
                        //'tooltip'   => 'Test',                                // If defined a tooltip will appear next to the header
                        'lookup'    => [
                            ['key' => 1, 'value' => 'Yes'],
                            ['key' => 0, 'value' => 'No'],
                        ],
                        'width'     => 3,
                    ]
                ]
            ],
            'house' => [
                'title'             => 'Houses',
                'mapper'            => \HouseMapper::class,
                'fields'            => [
                    'house'         => [
                        'caption'   => 'Name',
                        'width'     => 12,
                    ]
                ]
            ],
            'residential-house'    => [
                'title'             => 'Residential Houses',
                'mapper'            => \HouseResidentialMapper::class,
                'fields'            => [
                    'house'         => [
                        'caption'   => 'Name',
                        'width'     => 12,
                    ]
                ]
            ],
            'campus'    => [
                'title'             => 'Campus',
                'mapper'            => CampusMapper::class,
                'fields'            => [
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 6,
                    ],
                    'extTimetableCode' => [
                        'caption'   => 'External Timetable Code',
                        'width'     => 3,
                    ],
                    'timetableDay'  => [
                        'caption'   => 'Timetable Days',
                        'width'     => 3,
                    ]
                ]
            ],
            'event-type'           => [
                'title'             => 'Event Types',
                'mapper'            => EventTypeMapper::class,
                'fields'            => [
                    'type'          => [
                        'caption'   => 'Event Type',
                        'width'     => 10,
                    ],
                    'color'         => [
                        'caption'   => 'Event Colour',
                        'colour'    => true,
                        'width'     => 2,
                    ]
                ]
            ],
            'news-topic'             => [
                'title'             => 'News Topics',
                'mapper'            => TopicMapper::class,
                'sortable'          => 'sequence',
                'fields'            => [
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 11,
                    ],
                ]
            ],
            'submission-type'    => [
                'title'             => 'Submission Types',
                'mapper'            => WorkTypeMapper::class,
                'fields'            => [
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 12,
                    ],
                ]
            ],
            'submission-errors'     => [
                'title'             => 'Submission States',
                'mapper'            => \DueWork_ErrorMapper::class,
                'fields'            => [
                    'code'          => [
                        'caption'   => 'Code',
                        'width'     => 2,
                    ],
                    'displayAs'     => [
                        'caption'   => 'Display Name',
                        'width'     => 6,
                        'maxLength' => 16,
                    ],
                    'normaliseAs'   => [
                        'caption'   => 'Normalised Mark',
                        'nullable'  => true,
                        'width'     => 2,
                    ],
                    'isDefaultForNewSubmissions' => [
                        'caption'   => 'Default for new submissions?',
                        'radio'     => '1',
                        'width'     => 2,
                    ]
                ]
            ],
            'component-definition'  => [
                'title'             => 'Components',
                'mapper'            => \Component_DefinitionMapper::class,
                'findAllMethod'     => 'findAllIncludeHidden',
                'fields'            => [
                    'module'        => [
                        'caption'   => 'Module',
                        'readonly'  => true,
                        'width'     => 2,
                    ],
                    'name'          => [
                        'caption'   => 'Name',
                        'readonly'  => true,
                        'width'     => 2,
                    ],
                    'description'   => [
                        'caption'   => 'Description',
                        'readonly'  => true,
                        'width'     => 6,
                    ],
                    'visible'       => [
                        'caption'   => 'visible',
                        'lookup'    => [
                            ['key' => 'true', 'value' => 'Yes'],
                            ['key' => 'false', 'value' => 'No'],
                        ],
                        'width'     => 2,
                    ]
                ]
            ],
            'timetable-colour'   => [
                'title'             => 'Timetable Colours',
                'mapper'            => \Timetable_ColourMapper::class,
                'fields'            => [
                    'id'            => [
                        'getter'    => 'getTimetableColourId',
                        'hidden'    => true,
                    ],
                    'hex_colour'    => [
                        'caption'   => 'Event Colours',
                        'colour'    => true,
                        'width'     => 12
                    ],
                ]
            ],
            'pastoral-type'      => [
                'title'             => 'Pastoral Types',
                'mapper'            => TypeMapper::class,
                'sort'              => 'name',
                'fields'            => [
                    'id'          => [
                        'readonly'  => true,
                        'caption'   => 'Pastoral Type ID',
                        'width'     => 2,
                    ],
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 8,
                    ],
                    'externalCode' => [
                        'caption'   => 'SIS Code',
                        'width'     => 2,
                        'tooltip'   => 'This is used when exporting pastoral care records to your SIS pastoral care module. This should be the matching code in your SIS lookup table for this pastoral record type (e.g. luPastoralCareType). If you are not using (or do not want to export back to) your external SIS pastoral care module this can be left blank.',
                    ],
                ]
            ],
            'pastoral-category'    => [
                'title'             => 'Pastoral Sub-Types',
                'mapper'            => CategoryMapper::class,
                'sort'              => 'pastoralTypeId',
                'fields'            => [
                    'pastoralTypeId' => [
                        'getter'    => 'getPastoralTypeId',
                        'caption'   => 'Parent Pastoral Type ID',
                        'width'     => 2,
                        'tooltip'   => 'This is the ID number of the Pastoral Type under which this category will appear (refer to the \'Pastoral Types\' tab for a list of ID numbers).',
                    ],
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 8,
                    ],
                    'externalCode' => [
                        'caption'   => 'SIS Code',
                        'width'     => 2,
                        'tooltip'   => 'This is used when exporting pastoral care records to your SIS pastoral care module. This should be the matching code in your SIS lookup table for this pastoral record type (e.g. luPastoralCareCategory). If you are not using (or do not want to export back to) your external SIS pastoral care module this can be left blank.',
                    ],
                ]
            ],
            'pastoral-severity'    => [
                'title'             => 'Pastoral Record Severity',
                'mapper'            => SeverityMapper::class,
                'sortable'          => 'order',
                'fields'            => [
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 11,
                    ],
                ]
            ],
        ];
    }

    /**
     * Show options
     *
     * @api /adminv2/lists/save
     * @level 160
     *
     * @param string $table Arbitrary value to identify a table. Does not need to be the actual table name.
     */
    public function getIndex($table)
    {
        if (!isset($table)) {
            $table = "roles";
        }

        $tables = $this->getTableData();

        // Check for incorrect URLs
        if(!isset($tables[$table])){
            $this->getResponse()->status->setCode(404);
            return;
        }

        /** @var \Schoolbox\Mapper $mapper */
        $mapper     = new $tables[$table]['mapper'];
        $model_name = $mapper->getModel();
        $prototype  = new $model_name;

        $reflect    = new \ReflectionClass($prototype);
        $properties = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);

        $records = [];

        // Go through every row in the current lookup table

        if (isset($tables[$table]['findAllMethod'])) {
            $finder = $tables[$table]['findAllMethod'];
            $rows = $mapper->$finder();
        } else {
            $orderArr = array($mapper->getIdentifierColumn() => 'ASC');
            $rows = $mapper->findAll(array('order' => $orderArr));
        }

        if (isset($tables[$table]['sortable'])) {
            if ($reflect->hasMethod('get' . $tables[$table]['sortable'])) {
                $rows = $rows->sortBy($tables[$table]['sortable'], 'asc');
            }
        } else {
            if (isset($tables[$table]['sort'])) {
                if ($reflect->hasMethod('get' . $tables[$table]['sort'])) {
                    $rows = $rows->sortBy($tables[$table]['sort'], 'asc');
                }
            }
        }

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
                            $getter = "get" . $this->underscoreToCamelCase($prop->getName());

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

        $this->getResponse()->content->set([
            'template' => '@adminLists/index.twig',
            'data'     => [
                'pageCurrent' => 'lists',
                'tables'      => $tables,
                'table'       => $table,
                'records'     => $records
            ],
        ]);
    }
}
