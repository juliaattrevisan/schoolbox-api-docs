<?php
/**
 * Lists admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Lists;

use Alaress\Diff\Differ;
use Alaress\Web\Controller\Base;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\MapperFactory;
use Schoolbox\Pastoral\DocumentClassification\Model;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\CampusMapper;
use Component_DefinitionMapper;
use DueWork_ErrorMapper;
use HouseMapper;
use HouseResidentialMapper;
use ReflectionClass;
use ReflectionProperty;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Comms\News\Topic;
use Schoolbox\Sequential;
use Schoolbox\WebDav\Mapper as WebDavMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralCategoryMapper;
use Schoolbox\Pastoral\DocumentClassification\Mapper as PastoralDocumentClassificationMapper;
use Schoolbox\YearLevel;
use Timetable_ColourMapper;

class Controller extends Base
{
    /** @var MapperFactory */
    private $mapperFactory;


    /**
     * @param MapperFactory $mapperFactory
     */
    public function __construct(MapperFactory $mapperFactory)
    {
        $this->mapperFactory = $mapperFactory;
    }

    /**
     * Deletes a list item using the specified mapper
     */
    public function postDelete()
    {
        $result = false;
        $post = $this->getRequest()->post;

        $tableData = $this->getTableData();
        $mapperName = $tableData[$post['table']]['mapper'];

        /** @var Mapper $mapperName */
        if (class_exists($mapperName)) {
            /** @var Mapper $mapper */
            $mapper = $mapperName::getInstance();
            try {
                $record = $mapper->findById($post['id']);
                $result = $mapper->delete($record);
            } catch (MapperException $e) {
            }
        }

        $responseData = ['result' => $result];

        // Did deletion fail, and a message is defined to display on failure?
        if(!$result && isset($tableData[$post['table']]['deleteFailMsg'])){
            $responseData['message'] = $tableData[$post['table']]['deleteFailMsg'];
        }

        $this->getResponse()->content->set([
            'data'   => $responseData,
            'format' => 'json'
        ]);
    }

    /**
     * @api /adminv2/lists/save
     * @since 160
     */
    public function postSave()
    {
        $tableKey        = $this->getRequest()->post['table'];
        $tableData       = $this->getTableData();

        // Check for incorrect URLs
        if(!isset($tableData[$tableKey])){
            $this->getResponse()->status->setCode(404);
            return;
        }

        $mapper_name     = $tableData[$tableKey]['mapper'];

        $mapper = $this->mapperFactory->get($mapper_name);

        $ids    = [];
        $values = [];
        $fixed  = [];
        $nulls  = [];
        $radios = [];
        $checkboxes = [];
        // for mandatory text fields
        $mandatoryValues = [];

        // Get the data into some sort of usable format

        foreach ($this->getRequest()->post as $field => $value) {

            if (substr($field, 0, 6) == "value-") {
                $values[substr($field, 6)] = $value;
            } else if (substr($field, 0, 10) == "mandatory-") {
                $values[substr($field, 10)] = $value;
                $mandatoryValues[substr($field, 10)] = $value;
            } else if (substr($field, 0, 6) == "fixed-") {
                $fixed[substr($field, 6)] = $value;
            } else if (substr($field, 0, 5) == "null-") {
                $nulls[substr($field, 5)] = $value;
            } else if (substr($field, 0, 6) == "radio-") {
                $radios[substr($field, 6)] = $value;
            } else if (substr($field, 0, 9) == "checkbox-") {
                $checkboxes[substr($field, 9)] = $value;
            } else if ($field == "id") {
                $ids = $value;
            }
        }

        // List item entity is responsible for declaring its sequence base
        $sequence  = null;
        $modelName = $mapper->getModel();
        if (is_a($modelName, Sequential::class, true)) {
            /** @var Sequential $modelName */
            $sequence = $modelName::sequenceBase();
        }

        foreach ($ids as $currentIndex => $id)
        {
            // Negative ID numbers indicate new records
            if ($id > 0) {
                // Lookup existing record
                $model = $mapper->findById($id);
                $oldModel = clone $model;
            } else {
                // Create new record
                $model = $mapper->createModel();

                foreach ($fixed as $field => $value) {
                    $setter = $mapper->getSetMethodName($field);

                    if (empty(trim($value))) {
                        $value = null;
                    }

                    $model->$setter($value);
                }
            }

            // set literal values
            foreach ($values as $field => $value) {
                if (array_key_exists($field, $mandatoryValues) && trim($value[$currentIndex] ?? '') === '') {
                    // do not save this field, if required and empty
                    continue 2;
                }
                $getter = $mapper->getGetMethodName($field);
                if ($model->$getter() != $value[$currentIndex]) {

                    // Update values
                    $setter = $mapper->getSetMethodName($field);

                    $model->$setter($value[$currentIndex]);
                }
            }

            // Override nullable fields where appropriate
            foreach ($nulls as $field => $value) {
                $setter = $mapper->getSetMethodName($field);

                foreach ($value as $index => $_) {
                    if ($currentIndex == $index) {
                        $model->$setter(null);
                    }
                }
            }

            // Process any radio buttons for this list
            foreach ($radios as $field => $index) {
                $setter = $mapper->getSetMethodName($field);

                // Radio buttons can only be on for one row, so set that row to true and all others to false

                if ($id == $ids[$index]) {
                    $model->$setter(1);
                } else {
                    $model->$setter(0);
                }
            }

            //Set checkboxes whose that are checked
            foreach ($checkboxes as $field => $value) {
                $setter  = $mapper->getSetMethodName($field);
                $boolean = (bool)$value[$currentIndex];
                $model->$setter($boolean);
            }

            if (method_exists($model, $mapper->getGetMethodName('sequence')) && $model->getSequence() !== $sequence) {
                $model->setSequence($sequence);
            }
            $sequence++;

            // Update values if there is changes
            $diff = (new Differ())->diff($mapper->dehydrate($model), $mapper->dehydrate($oldModel));
            if (count($diff) > 0) {
                // Save the changes
                if (method_exists($model, $mapper->getGetMethodName('updatedBy'))) {
                    $model->setUpdatedBy($this->getUser()->getId());
                }
                $mapper->save($model);
            }
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
        $luEmpty = [
            ['key' => '', 'value' => ''],
        ];
        $luRoleType = [
            ['key' => Role::GUEST,   'value' => 'Guest'],
            ['key' => Role::STAFF,   'value' => 'Staff'],
            ['key' => Role::STUDENT, 'value' => 'Student'],
            ['key' => Role::PARENT,  'value' => 'Parent'],
        ];

        $luRole = [];
        $roles = RoleMapper::getInstance()->findAll();

        foreach ($roles as $role) {
            $luRole[] = [
                'key' => $role->getId(),
                'value' => $role->getName()
            ];
        }

        $luCampus = [];
        $campuses = CampusMapper::getInstance()->findAll();

        foreach ($campuses as $campus) {
            $luCampus[] = [
                'key' => $campus->getId(),
                'value' => $campus->getName()
            ];
        }

        // This array defines the tables that can be updated
        // Note that they are NOT indexed by actual table name, but by an arbitrary key.

        $pastoralTypes = PastoralTypeMapper::getInstance()->findAll();

        $luPastoralType = [];

        foreach ($pastoralTypes as $type) {
            $luPastoralType[] = [
                'key' => $type->getId(),
                'value' => $type->getName()
            ];
        }

        $documentClassifications = PastoralDocumentClassificationMapper::getInstance()->findAll();

        $luDocumentClassifications = [];

        $pastoralExternal = new \External_Pastoral();
        foreach ($documentClassifications as $classification) {
            /** @var Model $classification */
            $luDocumentClassifications[] = [
                'key' => $classification->getId(),
                'value' => $classification->getName()
            ];
        }

        $return = [
            'roles' => [
                'title'             => 'Roles',                                 // Title of the tab
                'delete'            => true,
                'deleteWarning'     => 'WARNING! Deleting users roles can have a serious impact on your system and may prevent access to various users/records throughout the system- please consult Schoolbox helpdesk if you require more information. Are you sure you want to delete this role?',
                'deleteFailMsg'     => 'You cannot delete a role that still has non-deleted users with that role',
                'mapper'            => RoleMapper::class,                       // Mapper for the list item
                //'findAllMethod'     => 'findAllIncludeHidden',                // By default it uses the findAll() method of the mapper- however you can override this. Necessary for 'components' page where findAll is overridden to not return items with 'visible = false'
                'fixed'             => [                                        // This is a list of fixed values added to new items, useful if there are hidden items that need default values
                    'digestSchedule' => '0'                                     // (e.g. Digest schedule cannot be null, so is hardcoded to 0 for new records)
                ],
                'fields'            => [                                        // This is a list of all of the updateable fields
                    'name'          => [
                        'caption'   => 'Name',                                  // Friendly caption that is displayed
                        'width'     => 7,                                       // Width of the column on anything bigger than 'small'
                        'maxLength' => 50,
                        'mandatory' => true,                                    // Set this true to avoid empty value for a text field.
                    ],
                    'type' => [
                        'caption'   => 'Role Type',
                        'lookup'    => $luRoleType,                             // If defined the column will appear as a select box
                        'width'     => 3,
                        'mandatory' => true,                                    // Set this true to remove an empty option from a drop down field.
                    ],
                    'canChangePassword' => [
                        'caption'   => 'Allow Password Change',
                        'lookup'    => [
                            ['key' => 1, 'value' => 'Yes'],
                            ['key' => 0, 'value' => 'No'],
                        ],
                        'width'     => 2,
                        'mandatory' => true,
                    ]
                ]
            ],
            'house' => [
                'title'             => 'Houses',
                'mapper'            => HouseMapper::class,
                'delete'            => true,
                'fields'            => [
                    'house'         => [
                        'caption'   => 'Name',
                        'width'     => 12,
                        'maxLength' => 50,
                        'mandatory' => true,
                    ]
                ]
            ],
            'residential-house'    => [
                'title'             => 'Residential Houses',
                'mapper'            => HouseResidentialMapper::class,
                'fields'            => [
                    'house'         => [
                        'caption'   => 'Name',
                        'width'     => 12,
                        'maxLength' => 255,
                        'mandatory' => true,
                    ]
                ]
            ],
            'webdav' => [
                'title'             => 'Drives',
                'delete'            => true,
                'mapper'            => WebDavMapper::class,
                'fields'            => [
                    'uri'           => [
                        'caption'   => 'Base URI',
                        'width'     => 3,
                        'mandatory' => true,
                    ],
                    'path'           => [
                        'caption'   => 'Path',
                        'width'     => 3,
                        'tooltip'   => 'The path underneath the base URI. Available tokens are {:yearlevel}, {:calendaryear}, {:graduationyear7}, {:graduationyear12}, {:graduationyeartwodigit7}, {:graduationyeartwodigit12}, {:startyear7}, {:startyear12} and {:username}.',
                    ],
                    'usernamePrefix' => [
                        'tooltip'   => 'String will be placed infront of username when authenticating, commonly a domain string like "domain/"',
                        'caption'   => 'User Prefix',
                        'width'     => 1,
                    ],
                    'usernameSuffix' => [
                        'tooltip'   => 'String will be placed at the end of username when authenticating, commonly a domain string like "@domain.edu"',
                        'caption'   => 'User Suffix',
                        'width'     => 1,
                    ],
                    'campus'        => [
                        'caption'   => 'Campus',
                        'tooltip'   => 'Drive will only display to users of this type, can be left blank',
                        'mandatory' => true,
                        'nullable'  => true,
                        'nullable_label' => 'N/A',
                        'width'     => 1,
                        'lookup'    => $luCampus,
                    ],
                    'roleType'      => [
                        'caption'   => 'Role Type',
                        'tooltip'   => 'Drive will only display to users of this type, can be left blank',
                        'mandatory' => true,
                        'nullable'  => true,
                        'nullable_label' => 'N/A',
                        'width'     => 1,
                        'lookup'    => $luRoleType,
                    ],
                    'role'        => [
                        'caption'   => 'Role',
                        'tooltip'   => 'Drive will only display to users of this type, can be left blank',
                        'mandatory' => true,
                        'nullable'  => true,
                        'nullable_label' => 'N/A',
                        'width'     => 1,
                        'lookup'    => $luRole,
                    ],
                    'enabled' => [
                        'caption'   => 'Enabled',
                        'width'     => 1,
                        'mandatory' => true,
                        'nullable_label' => 'N/A',
                        'lookup'    => [
                            ['key' => 1, 'value' => 'Yes'],
                            ['key' => 0, 'value' => 'No'],
                        ],
                    ],
                ]
            ],
            'campus'    => [
                'title'             => 'Campus',
                'mapper'            => CampusMapper::class,
                'fields'            => [
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 6,
                        'maxLength' => 255,
                        'mandatory' => true,
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
                        'maxLength' => 100,
                        'mandatory' => true,
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
                'mapper'            => Topic\Mapper::class,
                'fields'            => [
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 12,
                        'maxLength' => 150,
                        'mandatory' => true,
                    ],
                ]
            ],
            'submission-type'    => [
                'title'             => 'Work Types',
                'mapper'            => WorkTypeMapper::class,
                'delete'            => true,
                'fields'            => [
                    'showOnGradesPage'       => [
                        'caption'   => 'Grades page',
                        'tooltip'   => 'If checked, assessments with this work type will appear on the grades summary page.',
                        'checkbox'  =>  true,
                        'width'     => 1,
                        'mandatory' => true,
                    ],
                    'staffOrInstructorVisibleOnly' => [
                        'caption'   => 'Visible to Staff & Instructors only',
                        'tooltip'   => 'If checked, assessments with this work-type will only be visible to Staff or Instructors. Assessment marks will still be included in aggregate marks',
                        'checkbox'  => true,
                        'width'     => 2,
                        'mandatory' => true,
                    ],
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 6,
                        'maxLength' => 40,
                        'mandatory' => true,
                    ],
                    'externalResultType' => [
                        'caption'   => 'External SIS Type',
                        'width'     => 3,
                    ]
                ]
            ],
            'submission-errors'     => [
                'title'             => 'Submission States',
                'mapper'            => DueWork_ErrorMapper::class,
                'fields'            => [
                    'code'          => [
                        'caption'   => 'Code',
                        'width'     => 2,
                        'maxLength' => 4,
                    ],
                    'displayAs'     => [
                        'caption'   => 'Display Name',
                        'width'     => 8,
                        'maxLength' => 16,
                        'mandatory' => true,
                    ],
                    'normaliseAs'   => [
                        'caption'   => 'Normalised Mark',
                        'nullable'  => true,
                        'width'     => 2,
                    ],
                ]
            ],
            'component-definition'  => [
                'title'             => 'Components',
                'mapper'            => Component_DefinitionMapper::class,
                'findAllMethod'     => 'findAllIncludeHidden',
                'noNewRecords'      => true,
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
                        'caption'   => 'Visible',
                        'lookup'    => [
                            ['key' => 'true', 'value' => 'Yes'],
                            ['key' => 'false', 'value' => 'No'],
                        ],
                        'width'     => 2,
                        'mandatory' => true,
                    ]
                ]
            ],
            'timetable-colour'   => [
                'title'             => 'Timetable Colours',
                'mapper'            => Timetable_ColourMapper::class,
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
                'mapper'            => PastoralTypeMapper::class,
                'sort'              => 'name',
                'fields'            => [
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 6,
                        'maxLength' => 100,
                        'mandatory' => true,
                    ],
                    'documentClassification' => [
                        'getter'    => 'getDocumentClassification',
                        'caption'   => 'SIS Document Classification',
                        'lookup'    => $luDocumentClassifications,
                        'mandatory' => true,
                        'nullable'  => true,
                        'nullable_label' => 'No Document Classification',
                        'width'     => 3,
                        'tooltip'   => 'This is document classification code that will be assigned to uploaded documents in your SIS for records of this type. These are defined in the Pastoral Document Classifications list.',
                    ],
                    'externalCode' => [
                        'caption'   => 'SIS Code',
                        'lookup'    => $pastoralExternal->getTypeList(),
                        'nullable'  => true,
                        'mandatory' => false,
                        'nullable_label' => 'N/A',
                        'width'     => 3,
                        'tooltip'   => 'This is used when exporting pastoral care records to your SIS pastoral care module. This should be the matching code in your SIS lookup table for this pastoral record type (e.g. luPastoralCareType). If you are not using (or do not want to export back to) your external SIS pastoral care module this can be left blank.',
                    ],
                ]
            ],
            'pastoral-category'    => [
                'title'             => 'Pastoral Sub-Types',
                'mapper'            => PastoralCategoryMapper::class,
                'sort'              => 'pastoral_type_id',
                'fields'            => [
                    'pastoralType' => [
                        'getter'    => 'getPastoralType',
                        'caption'   => 'Pastoral Type',
                        'lookup'    => $luPastoralType,
                        'mandatory' => true,
                        'width'     => 2,
                        'tooltip'   => 'This is the pastoral type under which this sub-type will appear.',
                    ],
                    'name'          => [
                        'caption'   => 'Name',
                        'mandatory' => true,
                        'width'     => 8,
                        'maxLength' => 100,
                    ],
                    'externalCode' => [
                        'caption'   => 'SIS Code',
                        'lookup'    => $pastoralExternal->getSubtypeList(),
                        'nullable'  => true,
                        'mandatory' => false,
                        'nullable_label' => 'N/A',
                        'width'     => 2,
                        'tooltip'   => 'This is used when exporting pastoral care records to your SIS pastoral care module. This should be the matching code in your SIS lookup table for this pastoral record type (e.g. luPastoralCareCategory). If you are not using (or do not want to export back to) your external SIS pastoral care module this can be left blank.',
                    ],
                ]
            ],
            'pastoral-document-classification' => [
                'title'             => 'Pastoral SIS Document Classifications',
                'mapper'            => PastoralDocumentClassificationMapper::class,
                'sort'              => 'name',
                'fields'            => [
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 10,
                        'maxLength' => 100,
                        'mandatory' => true,
                    ],
                    'externalCode' => [
                        'caption'   => 'SIS Code',
                        'width'     => 2,
                        'lookup'    => $pastoralExternal->getDocumentClassificationList(),
                        'nullable'  => true,
                        'mandatory' => false,
                        'nullable_label' => 'N/A',
                        'tooltip'   => 'This is used when exporting pastoral care records to your SIS pastoral care module. This should be the matching code in your SIS lookup table for this pastoral record type (e.g. luDocumentClassification). If you are not using (or do not want to export back to) your external SIS pastoral care module this can be left blank.',
                    ],
                ]
            ],
        ];

        // If pastoral severities are disabled by policy, don't display item on the list screen

        if ($this->getConfigLoader()->get('pastoral_severity')) {
            $return['pastoral-severity'] = [
                'title' => 'Pastoral Record Severity',
                'mapper' => PastoralSeverityMapper::class,
                'fields' => [
                    'name' => [
                        'caption' => 'Name',
                        'width' => 8,
                        'maxLength' => 100,
                        'mandatory' => true,
                    ],
                    'dashboard' => [
                        'caption' => 'Show On Pastoral Dashboard',
                        'width' => 4,
                        'mandatory' => true,
                        'lookup' => [
                            ['key' => '1', 'value' => 'Yes'],
                            ['key' => '0', 'value' => 'No'],
                        ],
                    ],
                ]
            ];
        }

        $return['year-levels'] = [
            'title'  => 'Year Levels',
            'delete' => true,
            'mapper' => YearLevel\Mapper::class,
            'fields' => [
                YearLevel\Mapper::COL_NAME => [
                    'caption'   => 'Name',
                    'width'     => 12,
                    'maxLength' => 150,
                    'mandatory' => true,
                ],
            ],
        ];

        return $return;
    }

    /**
     * Show options
     *
     * @api /adminv2/lists
     * @since 160
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

        /** @var Mapper $mapper */
        $mapper     = new $tables[$table]['mapper'];
        $model_name = $mapper->getModel();
        $prototype  = new $model_name;

        $reflect    = new ReflectionClass($prototype);
        $properties = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);

        $records = [];

        // Go through every row in the current lookup table

        if (isset($tables[$table]['findAllMethod'])) {
            $finder = $tables[$table]['findAllMethod'];
            $rows = $mapper->$finder();
        } elseif (isset($tables[$table]['sort'])) {
            $rows = $mapper->findAll(['order' => [$tables[$table]['sort'] => 'ASC']]);
        } elseif ($prototype instanceof Sequential) {
            $rows = $mapper->findAll(['order' => ['sequence' => 'ASC']]);
            $tables[$table]['sortable'] = true;
        } else {
            $orderArr = array($mapper->getIdentifierColumn() => 'ASC');
            $rows = $mapper->findAll(array('order' => $orderArr));
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

                        if (isset($tables[$table]['fields'][$prop->getName()]['getter'])) {
                            $getter = $tables[$table]['fields'][$prop->getName()]['getter'];
                        } else {
                            $getter = $mapper->getGetMethodName($prop->getName());
                        }

                        if ($reflect->hasMethod($getter)) {

                            if (isset($tables[$table]['fields']['id']['getter'])) {
                                $idGetter = $tables[$table]['fields']['id']['getter'];
                            } else {
                                $idGetter = 'getId';
                            }

                            $id = $row->$idGetter();

                            if (!isset($records[$id])) {
                                $records[$id] = [];
                            }

                            $value = $row->$getter();

                            // If we get an object back, we really want its ID

                            if (is_object($value)) {
                                /** @noinspection PhpUndefinedMethodInspection */
                                $value = $value->getId();
                            }

                            $records[$id][$prop->getName()] = $value;
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
