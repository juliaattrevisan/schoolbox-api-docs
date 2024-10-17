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
use Aura\Router\Exception\RouteNotFound;
use Exception;
use External_Pastoral;
use External_Timetable;
use ReflectionException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeLevelGroupMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Moment\Evidence\Tag\Mapper as LearningMomentEvidenceTag;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\MapperFactory;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\CampusMapper;
use Component_DefinitionMapper;
use Schoolbox\Learning\DueWork\StateMapper;
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
use Schoolbox\Pastoral\Type\Model as PastoralTypeModel;
use Schoolbox\Pastoral\SubType\Mapper as PastoralCategoryMapper;
use Schoolbox\Pastoral\DocumentClassification\Mapper as PastoralDocumentClassificationMapper;
use Schoolbox\Pastoral;
use Schoolbox\YearLevel;
use Timetable_ColourMapper;
use Twig\Error\Error;
use UnexpectedValueException;

/**
 * Class Controller
 * @package Schoolbox\Admin\Lists
 */
class Controller extends Base
{
    /** @var MapperFactory */
    private MapperFactory $mapperFactory;

    /**
     * Note to anyone thinking about using the RosettaStone here to fix terminology:
     *  - Terminology should not be used in the admin system. It creates a recursive problem that cannot be solved. We
     *    use terminology only in user facing areas, in admin areas we use the original terminology to avoid any
     *    confusion when things are renamed.  Example: You change the language on your phone, and then you can't find
     *    the menu to change the language back because its in the foreign language.
     *
     * @param MapperFactory $mapperFactory
     */
    public function __construct(MapperFactory $mapperFactory)
    {
        $this->mapperFactory = $mapperFactory;
    }

    /**
     * Deletes a list item using the specified mapper
     */
    public function postDelete(): void
    {
        $this->postDeleteSlave($this->requestPost->get('table', ''));
    }

    /**
     * @param string $table
     * @return void
     */
    public function postNewsDelete(string $table): void
    {
        $this->postDeleteSlave($table);
    }

    /**
     * @param string $table
     * @return void
     */
    private function postDeleteSlave(string $table): void
    {
        $result = false;
        $post = $this->requestPost;

        $tableData = $this->getTableData();
        $mapperName = $tableData[$table]['mapper'];

        if (class_exists($mapperName)) {
            /** @var Mapper $mapper */
            $mapper = $mapperName::getInstance();
            try {
                $record = $mapper->findById($post['id']);
                $result = $mapper->delete($record);
            } catch (MapperException) {
                // Something failed in the mapper: noop

                // Deletion _can_ raise UnexpectedValueException
                /** @phpstan-ignore-next-line */
            } catch (UnexpectedValueException) {
                // Something else unexpected happened, likely thrown by a
                // pre-delete: noop
            }
        }

        $responseData = ['result' => $result];

        // Did deletion fail, and a message is defined to display on failure?
        if (!$result && isset($tableData[$table]['deleteFailMsg'])) {
            $responseData['message'] = $tableData[$table]['deleteFailMsg'];
        }

        $this->response = $this->responseAsJson($responseData);
    }

    /**
     * @throws MapperException
     * @throws Deleted
     * @since    160
     * @api      /adminv2/lists/save
     * @return void
     */
    public function postSave(): void
    {
        $this->postSaveSlave($this->requestPost->get('table', ''));
    }

    /**
     * @param string $table
     * @return void
     * @throws Deleted
     * @throws MapperException
     * @since    160
     * @api      /adminv2/lists/save
     */
    public function postNewsSave(string $table): void
    {
        $this->postSaveSlave($table);
    }

    /**
     * @param string $tableKey
     * @return void
     * @throws Deleted
     * @throws MapperException
     */
    private function postSaveSlave(string $tableKey): void
    {
        $tableData = $this->getTableData();

        // Check for incorrect URLs
        if (!isset($tableData[$tableKey])) {
            $this->response = $this->responseEmpty(404);
            return;
        }

        // Reformat to rows to field -> values
        if ($this->requestPost['rows']) {
            $fieldValues = $this->processRows($tableData[$tableKey]['fields'], $this->requestPost['rows']);
        } else {
            $fieldValues = $this->requestPost;
        }

        $mapper_name     = $tableData[$tableKey]['mapper'];

        /** @var Mapper $mapper */
        $mapper = $this->mapperFactory->get($mapper_name);

        $ids    = [];
        $values = [];

        // Get the data into some sort of usable format

        foreach ($fieldValues as $field => $value) {
            if ($field == "id") {
                $ids = $value;
            } else {
                // TODO: new format
                $values[$field] = $value;
            }
        }

        // ids of newly created models
        $newIds = [];
        foreach ($ids as $currentIndex => $id) {
            // Negative ID numbers indicate new records
            if ($id > 0) {
                // Lookup existing record
                $model = $mapper->findById($id);
                $oldModel = clone $model;
            } else {
                // Create new record
                $setId = $mapper->getSetIdentifierMethodName();
                $model = $mapper->createModel();
                if (method_exists($model, $setId)) {
                    $model->$setId(0);
                }
                $oldModel = null;
            }

            // set literal values
            foreach ($values as $field => $value) {
                $getter = $mapper->getGetMethodName($field);
                if (!method_exists($model, $getter)) {
                    continue;
                }

                if ($model->$getter() !== $value[$currentIndex]) {
                    // Update values
                    $setter = $mapper->getSetMethodName($field);
                    if (!method_exists($model, $setter)) {
                        continue;
                    }

                    if (is_string($value[$currentIndex])) {
                        $value[$currentIndex] = trim($value[$currentIndex]);
                    }

                    // Some setters expect objects as parameters.  For those cases we need to create objects.
                    $valueToSet = match ($setter) {
                        'setPastoralType' => (new PastoralTypeModel())->setId($value[$currentIndex]),
                        default => $value[$currentIndex],
                    };

                    $model->$setter($valueToSet);
                }
            }

            // Update values if there is changes
            $persist = true;
            if ($oldModel) {
                $diff = (new Differ())->diff($mapper->dehydrate($model), $mapper->dehydrate($oldModel));
                $persist = count($diff) > 0;
            }
            if ($persist) {
                // Save the changes
                if (method_exists($model, $mapper->getGetMethodName('updatedBy'))) {
                    $model->setUpdatedBy($this->getUser()->getId());
                }

                /* Set the Created By field, if it exists, as this records who created
                 * this row item. updatedBy a couple lines above uses an int instead of
                 * a user instance for some reason (probably for compatibility across
                 * multiple coding styles throughout schoolbox)
                 */
                $setCreatedByMethod = $mapper->getSetMethodName('createdBy');

                if (method_exists($model, $setCreatedByMethod)) {
                    $model->$setCreatedByMethod($this->getUser());
                }

                try {
                    $affectedIds = $mapper->save($model);
                    if (null === $oldModel) {
                        $newIds = array_merge($newIds, $affectedIds);
                    }
                } catch (Exception $e) {
                    throw new SchoolboxException('Could not save: ' . $e->getMessage());
                }
            }
        }
        if (!empty($newIds)) {
            $this->response = $this->responseAsJson(
                [
                    'newRowIdList' => $newIds,
                ]
            );
            return;
        }

        $this->response = $this->redirectAfterPost('/adminv2/lists/' . $this->requestPost["table"]);
    }

    /**
     * @param array  $fields
     * @param string $data
     * @return array
     */
    private function processRows(array $fields, string $data): array
    {
        $fieldNames = array_keys($fields);

        // add "id" as it's not in fields
        if (!in_array('id', $fieldNames)) {
            $fieldNames[] = 'id';
        }
        if (!in_array('sequence', $fieldNames)) {
            $fieldNames[] = 'sequence';
        }
        $fieldValues = [];
        $rows        = json_decode($data, true);
        foreach ($rows as $row) {
            foreach ($fieldNames as $fieldName) {
                // if current field is nullable (defined in getTableData)
                // and the value passed in is empty string, convert it to null
                $nullable   = $fields[$fieldName]['nullable'] ?? false;
                $fieldValue = $row[$fieldName] ?? null;
                $fieldValue = is_string($fieldValue) ? trim($fieldValue) : $fieldValue;
                if ($nullable && $fieldValue === '') {
                    $fieldValue = null;
                }
                $fieldValues[$fieldName][] = $fieldValue;
            }
        }
        return $fieldValues;
    }

    /**
     * Gets all table settings
     *
     * @return array
     */
    private function getTableData(): array
    {
        $luRoleType = [
            ['key' => Role::GUEST,   'value' => 'Guest'],
            ['key' => Role::STAFF,   'value' => 'Staff'],
            ['key' => Role::STUDENT, 'value' => 'Student'],
            ['key' => Role::PARENT,  'value' => 'Parent'],
        ];

        $luRole = [];
        $roles  = RoleMapper::getInstance()->findAll(['deleted' => true]);

        foreach ($roles as $role) {
            $luRole[] = [
                'key'     => $role->getId(),
                'value'   => $role->getName(),
                'deleted' => $role->isDeleted(),
            ];
        }

        $luCampus = [];
        $campuses = CampusMapper::getInstance()->findAll(['deleted' => true]);
        foreach ($campuses as $campus) {
            $luCampus[] = [
                'key'     => $campus->getId(),
                'value'   => $campus->getName(),
                'deleted' => $campus->isDeleted(),
            ];
        }

        // This array defines the tables that can be updated
        // Note that they are NOT indexed by actual table name, but by an arbitrary key.

        $pastoralTypes  = PastoralTypeMapper::getInstance()->findAll(['deleted' => true]);
        $luPastoralType = [];
        foreach ($pastoralTypes as $type) {
            $luPastoralType[] = [
                'key'     => $type->getId(),
                'value'   => $type->getName(),
                'deleted' => $type->isDeleted(),
            ];
        }

        $documentClassifications   = PastoralDocumentClassificationMapper::getInstance()->findAll(['deleted' => true]);
        $luDocumentClassifications = [];
        $pastoralExternal          = new External_Pastoral();
        foreach ($documentClassifications as $classification) {
            $luDocumentClassifications[] = [
                'key'     => $classification->getId(),
                'value'   => $classification->getName(),
                'deleted' => $classification->isDeleted(),
            ];
        }

        /* Available Timetable Cycles from your current connector
         */
        $luCycles = (new External_Timetable())->findCycleLookup();

        $return = [
            'roles' => [
                'title'             => 'Roles',                                 // Title of the tab
                'delete'            => true,
                'deleteWarning'     => 'WARNING! Deleting users roles can have a serious impact on your system and may prevent access to various users/records throughout the system- please consult Schoolbox helpdesk if you require more information. Are you sure you want to delete this role?',
                'deleteFailMsg'     => 'You cannot delete a role that still has non-deleted users with that role',
                'mapper'            => RoleMapper::class,                       // Mapper for the list item
                'showId'            => true,                                    // Set this to true to display the db ID on the front end.
                'fields'            => [                                        // This is a list of all the updatable fields
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,                                    // set field to readonly, so the user can not modify the db value...
                    ],
                    'name'          => [
                        'caption'   => 'Name',                                  // Friendly caption that is displayed
                        'width'     => 2,                                       // Width of the column on anything bigger than 'small'
                        'maxLength' => 50,
                        'mandatory' => true,                                    // Set this true to avoid empty value for a text field.
                        'default'   => '',
                    ],
                    'type' => [
                        'caption'   => 'Role Type',
                        'lookup'    => $luRoleType,                             // If defined the column will appear as a select box
                        'width'     => 2,
                        'mandatory' => true,                                    // Set this true to remove an empty option from a drop-down field.
                    ],
                    'permissions' => [
                        'caption'             => 'Permissions',
                        'key'                 => 'permissions',
                        'width'               => 7,
                        'tooltip'             => 'Permissions can be customised for each staff role. This can limit the visibility and access to parts of the instance.',
                        'isPopOutList'        => true,
                        'changePopOut'        => 'type',
                        'changePopOutOptions' => Role::VALID_PERMISSIONS,
                    ],
                    'canChangePassword' => [
                        'caption'    => 'Allow Password Change',
                        'checkbox'   => true,
                        'mandatory'  => true,
                        'default'    => false,
                        'popOutList' => 'permissions',
                    ],
                    'viewStudentProfile' => [
                        'caption'    => 'View Student Profile',
                        'checkbox'   => true,
                        'mandatory'  => true,
                        'default'    => false,
                        'popOutList' => 'permissions',
                    ],
                    'viewStudentGrades' => [
                        'caption'    => 'View Student Grades',
                        'checkbox'   => true,
                        'mandatory'  => true,
                        'default'    => false,
                        'popOutList' => 'permissions',
                    ],
                    'viewStudentCalendar' => [
                        'caption'    => 'View Student Calendar',
                        'checkbox'   => true,
                        'mandatory'  => true,
                        'default'    => false,
                        'popOutList' => 'permissions',
                    ],
                    'viewParentProfile' => [
                        'caption'    => 'View Parent Profile',
                        'checkbox'   => true,
                        'mandatory'  => true,
                        'default'    => false,
                        'popOutList' => 'permissions',
                    ],
                    'accessEportfolio' => [
                        'caption'    => 'Access ePortfolio',
                        'checkbox'   => true,
                        'mandatory'  => true,
                        'default'    => false,
                        'popOutList' => 'permissions',
                    ],
                    'accessPastoral' => [
                        'caption'    => 'Access Pastoral Care',
                        'checkbox'   => true,
                        'mandatory'  => true,
                        'default'    => false,
                        'popOutList' => 'permissions',
                    ],
                    'createBadges' => [
                        'caption'    => 'Create Badges',
                        'checkbox'   => true,
                        'mandatory'  => true,
                        'default'    => false,
                        'popOutList' => 'permissions',
                    ],
                    'accessEvidence' => [
                        'caption'    => 'Learning Moments',
                        'checkbox'   => true,
                        'mandatory'  => true,
                        'default'    => false,
                        'popOutList' => 'permissions',
                    ],
                    'viewParentDirectory' => [
                        'caption'    => 'View Parent Directory',
                        'checkbox'   => true,
                        'mandatory'  => true,
                        'default'    => false,
                        'popOutList' => 'permissions',
                    ],
                    'viewStaffDirectory' => [
                        'caption'    => 'View Staff Directory',
                        'checkbox'   => true,
                        'mandatory'  => true,
                        'default'    => false,
                        'popOutList' => 'permissions',
                    ],
                ]
            ],
            'house' => [
                'title'             => 'Houses',
                'mapper'            => HouseMapper::class,
                'delete'            => true,
                'showId'            => true,
                'fields'            => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
                    'house'         => [
                        'caption'   => 'Name',
                        'width'     => 11,
                        'maxLength' => 50,
                        'mandatory' => true,
                        'default'   => '',
                    ]
                ]
            ],
            'residential-house'    => [
                'title'             => 'Residential Houses',
                'mapper'            => HouseResidentialMapper::class,
                'delete'            => true,
                'showId'            => false,
                'fields'            => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
                    'house'         => [
                        'caption'   => 'Name',
                        'width'     => 11,
                        'maxLength' => 255,
                        'mandatory' => true,
                        'default'   => '',
                    ]
                ]
            ],
            'webdav' => [
                'title'             => 'Drives',
                'delete'            => true,
                'mapper'            => WebDavMapper::class,
                'showId'            => false,
                'fields'            => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
                    'uri'           => [
                        'caption'   => 'Base URI',
                        'width'     => 2,
                        'mandatory' => true,
                        'default'   => '',
                    ],
                    'path'          => [
                        'caption'   => 'Path',
                        'width'     => 3,
                        'tooltip'   => 'The path underneath the base URI. Available tokens are {:yearlevel}, {:calendaryear}, {:graduationyear7}, {:graduationyear12}, {:graduationyeartwodigit7}, {:graduationyeartwodigit12}, {:startyear7}, {:startyear12} and {:username}.',
                        'default'   => '',
                    ],
                    'usernamePrefix' => [
                        'tooltip'   => 'String will be placed in front of username when authenticating, commonly a domain string like "domain/"',
                        'caption'   => 'User Prefix',
                        'width'     => 1,
                        'default'   => '',
                    ],
                    'usernameSuffix' => [
                        'tooltip'   => 'String will be placed at the end of username when authenticating, commonly a domain string like "@domain.edu"',
                        'caption'   => 'User Suffix',
                        'width'     => 1,
                        'default'   => '',
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
                    'role'          => [
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
                        'lookup' => [
                            ['key' => 1, 'value' => 'Yes'],
                            ['key' => 0, 'value' => 'No'],
                        ],
                    ],
                ]
            ],
            'campus' => [
                'title'             => 'Campus',
                'mapper'            => CampusMapper::class,
                'delete'            => true,
                'showId'            => true,
                'deleteFailMsg'     => 'This campus cannot be deleted as it has users assigned to it.',
                'fields'            => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 5,
                        'maxLength' => 255,
                        'mandatory' => true,
                        'default'   => '',
                    ],
                    'extTimetableCode' => [
                        'caption'   => 'External Timetable Code',
                        'mandatory' => false,
                        'nullable'  => true,
                        'nullable_label' => 'N/A',
                        'width'     => 5,
                        'lookup'    => $luCycles,
                    ],
                    'timetableDay'  => [
                        'caption'   => 'Timetable Days',
                        'width'     => 1,
                        'nullable'  => true,
                        'default'   => null,
                    ]
                ]
            ],
            'event-type'            => [
                'title'             => 'Event Types',
                'delete'            => true,
                'mapper'            => EventTypeMapper::class,
                'showId'            => false,
                'fields'            => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
                    'type'          => [
                        'caption'   => 'Event Type',
                        'width'     => 10,
                        'maxLength' => 100,
                        'mandatory' => true,
                        'default'   => '',
                    ],
                    'color'         => [
                        'caption'   => 'Event Colour',
                        'colour'    => true,
                        'width'     => 1,
                        'default'   => '#000000',
                    ]
                ]
            ],
            'news-topic'            => [
                'title'             => 'News Topics',
                'delete'            => true,
                'mapper'            => Topic\Mapper::class,
                'showId'            => false,
                'fields'            => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 11,
                        'maxLength' => 150,
                        'mandatory' => true,
                        'default'   => '',
                    ],
                ]
            ],
            'submission-type'       => [
                'title'             => 'Work Types',
                'mapper'            => WorkTypeMapper::class,
                'delete'            => true,
                'showId'            => false,
                'fields'            => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
                    'showOnGradesPage'       => [
                        'caption'   => 'Grades page',
                        'tooltip'   => 'If checked, assessments with this work type will appear on the grades summary page.',
                        'checkbox'  =>  true,
                        'width'     => 1,
                        'default'   => false,
                        'tooltipPosition' => 'right',
                    ],
                    'staffOrInstructorVisibleOnly' => [
                        'caption'   => 'Visible to Staff & Instructors only',
                        'tooltip'   => 'If checked, assessments with this work-type will only be visible to Staff or Instructors. Assessment marks will still be included in aggregate marks',
                        'checkbox'  => true,
                        'width'     => 2,
                        'default'   => false,
                        'tooltipPosition' => 'right',
                    ],
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 6,
                        'maxLength' => 40,
                        'mandatory' => true,
                        'default'   => '',
                    ],
                    'externalResultType' => [
                        'caption'   => 'External SIS Type',
                        'width'     => 2,
                        'default'   => '',
                    ]
                ]
            ],
            'submission-errors'     => [
                'title'             => 'Submission States',
                'mapper'            => StateMapper::class,
                'delete'            => true,
                'showId'            => false,
                'fields'            => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
                    'code'          => [
                        'caption'   => 'Code',
                        'width'     => 2,
                        /* This is intentionally shorter than what the db schema allows (8)
                         * to...artificially inhibit users' creativity here. Because this is
                         * php-land, we can expand it on-demand in a master branch.
                         */
                        'maxLength' => 6,
                        'default'   => '',
                    ],
                    'displayAs'     => [
                        'caption'   => 'Display Name',
                        'width'     => 7,
                        /* This is intentionally shorter than what the db schema allows (64)
                         * to...artificially inhibit users' creativity here. Because this is
                         * php-land, we can expand it on-demand in a master branch.
                         */
                        'maxLength' => 48,
                        'mandatory' => true,
                        'default'   => '',
                    ],
                    'normaliseAs'   => [
                        'caption'   => 'Normalised Grade', // For consistency, 'normalised grade' should be used instead of 'normalised mark' in the UI.
                        'tooltip'   => 'Add a value between 0 or 100 that will be awarded on the assessment of a learning activity. Leaving the field blank will result in a NULL value when the submission state is selected',
                        'nullable'  => true,
                        'width'     => 2,
                        'default'   => null,
                    ],
                ]
            ],
            'component-definition'  => [
                'title'             => 'Components',
                'mapper'            => Component_DefinitionMapper::class,
                'findAllMethod'     => 'findAllIncludeHidden',
                'noNewRecords'      => true,
                'showId'            => false,
                'fields'            => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
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
                        'width'     => 1,
                        'checkbox'  => true,
                        'default'   => false,
                    ]
                ]
            ],
            'timetable-colour'   => [
                'title'             => 'Timetable Colours',
                'mapper'            => Timetable_ColourMapper::class,
                'showId'            => false,
                'fields'            => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                        'getter'    => 'getTimetableColourId',
                    ],
                    'hexColour'    => [
                        'caption'   => 'Event Colours',
                        'colour'    => true,
                        'width'     => 11,
                        'default'   => '#000000',
                    ],
                ]
            ],
            'pastoral-type'      => [
                'title'             => 'Pastoral Types',
                'mapper'            => PastoralTypeMapper::class,
                'delete'            => true,
                'deleteFailMsg'     => 'You cannot delete a pastoral type which is still in use in a pastoral record.',
                'showId'            => false,
                'fields'            => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 6,
                        'maxLength' => 100,
                        'mandatory' => true,
                        'default'   => '',
                    ],
                    'documentClassification' => [
                        'getter'    => 'getDocumentClassification',
                        'caption'   => 'SIS Document Classification',
                        'lookup'    => $luDocumentClassifications,
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
                        'width'     => 2,
                        'tooltip'   => 'This is used when exporting pastoral care records to your SIS pastoral care module. This should be the matching code in your SIS lookup table for this pastoral record type (e.g. luPastoralCareType). If you are not using (or do not want to export back to) your external SIS pastoral care module this can be left blank.',
                    ],
                ]
            ],
            'pastoral-category'    => [
                'title'             => 'Pastoral Sub-Types',
                'mapper'            => PastoralCategoryMapper::class,
                'delete'            => true,
                'showId'            => false,
                'deleteFailMsg'     => 'You cannot delete a pastoral sub-type which is still in use in a pastoral record.',
                'fields'            => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
                    'pastoralType' => [
                        'getter'    => 'getPastoralType',
                        'caption'   => 'Pastoral Type',
                        'lookup'    => $luPastoralType,
                        'mandatory' => true,
                        'width'     => 2,
                        'tooltip'   => 'This is the pastoral type under which this sub-type will appear.',
                        'tooltipPosition' => 'right',
                    ],
                    'name'          => [
                        'caption'   => 'Name',
                        'mandatory' => true,
                        'width'     => 7,
                        'maxLength' => 100,
                        'default'   => '',
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
                'delete'            => true,
                'showId'            => false,
                'fields'            => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
                    'name'          => [
                        'caption'   => 'Name',
                        'width'     => 9,
                        'maxLength' => 100,
                        'mandatory' => true,
                        'default'   => '',
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

        /* Revert back to a freeform input field if $luCycles is empty and
         * you can't choose anything (dropdown field is kind of useless)
         */
        if (!count($luCycles)) {
            unset(
                $return['campus']['fields']['extTimetableCode']['nullable'],
                $return['campus']['fields']['extTimetableCode']['nullable_label'],
                $return['campus']['fields']['extTimetableCode']['lookup']
            );
        }

        // If pastoral severities are disabled by policy, don't display item on the list screen

        if ($this->getConfigLoader()->get('pastoral_severity')) {
            $return['pastoral-severity'] = [
                'title'  => 'Pastoral Record Severity',
                'mapper' => PastoralSeverityMapper::class,
                'delete' => true,
                'showId' => false,
                'fields' => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
                    'name' => [
                        'caption' => 'Name',
                        'width' => 8,
                        'maxLength' => 100,
                        'mandatory' => true,
                        'default'   => '',
                    ],
                    'dashboard' => [
                        'caption'   => 'Show On Pastoral Dashboard',
                        'width'     => 2,
                        'checkbox'  => true,
                        'default'   => false,
                    ],
                    'color'         => [
                        'caption'   => 'Severity Colour',
                        'colour'    => true,
                        'width'     => 1,
                        'default'   => null,
                    ],
                ]
            ];
        }

        /* Allow superusers to add/remove pastoral tags
         */
        $return['pastoral-tags'] = [
            'title' => 'Pastoral Record Tags',
            'mapper' => Pastoral\Tag\Mapper::class,
            'delete' => true,
            'deleteWarning' => 'Deleting pastoral tags may affect existing reports. Are you sure?',
            'showId'   => false,
            'fields' => [
                'id' => [
                    'caption'   => 'ID',
                    'width'     => 1,
                    'readonly'  => true,
                ],
                'name' => [
                    'caption'   => 'Name',
                    'width'     => 11,
                    'maxLength' => 100,
                    'mandatory' => true,
                    'default'   => '',
                ],
            ]
        ];
        if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
            $return['pastoral-action'] = [
                'title'         => 'Pastoral Actions',
                'mapper'        => Pastoral\Action\Mapper::class,
                'delete'        => true,
                'deleteWarning' => 'Are you sure?',
                'showId'        => false,
                'fields'        => [
                    'id' => [
                        'caption'   => 'ID',
                        'width'     => 1,
                        'readonly'  => true,
                    ],
                    'name'         => [
                        'caption'   => 'Name',
                        'width'     => 9,
                        'maxLength' => 100,
                        'mandatory' => true,
                        'default'   => '',
                    ],
                    'externalCode' => [
                        'caption'        => 'SIS Code',
                        'width'          => 2,
                        'lookup'         => $this->getConfigLoader()->get('external_pastoral') ? $pastoralExternal->getActionList() : [],
                        'nullable'       => true,
                        'mandatory'      => false,
                        'nullable_label' => 'N/A',
                        'tooltip'        => 'This is used when exporting pastoral care records to your SIS pastoral care module. This should be the matching code in your SIS lookup table for this pastoral record type (e.g. luDocumentClassification). If you are not using (or do not want to export back to) your external SIS pastoral care module this can be left blank.',
                    ],
                ],
            ];
        }

        $gradeList   = GradeLevelGroupMapper::getInstance()->findAllForAdminOption();
        $luGradeList = [];
        foreach ($gradeList as $grade) {
            $luGradeList[] = [
                'key'   => $grade->getId(),
                'value' => $grade->getName(),
            ];
        }

        $return['year-levels'] = [
            'title'  => 'Year Levels',
            'delete' => true,
            'showId' => true,
            'mapper' => YearLevel\Mapper::class,
            'fields' => [
                'id' => [
                    'caption'   => 'ID',
                    'width'     => 1,
                    'readonly'  => true,
                ],
                YearLevel\Mapper::COL_NAME => [
                    'caption'   => 'Name',
                    'width'     => 1,
                    'maxLength' => 150,
                    'mandatory' => true,
                    'default'   => '',
                ],
                'overallGrade'             => [
                    'caption'   => 'Overall Grade',
                    'tooltip'   => 'Controls the default method for displaying the students overall grade on the grade report for a subject with this year level.',
                    'lookup'    => $luGradeList,
                    'width'     => 2,
                    'mandatory' => false,
                    'tooltipPosition' => 'right'
                ],
                'displayOptions' => [
                    'caption'             => 'Display Options',
                    'key'                 => 'displayOptions',
                    'width'               => 8,
                    'isPopOutList'        => true,
                ],
                'showOverallGrade'         => [
                    'caption'    => 'Display Overall Grade',
                    'tooltip'    => 'If checked, the grade average for a subject with this year level will be displayed to students and their parents on the student\'s grades page.',
                    'checkbox'   => true,
                    'mandatory'  => true,
                    'default'    => false,
                    'popOutList' => 'displayOptions',
                ],
                'displayGradePage'         => [
                    'caption'    => 'Display Grade Page',
                    'checkbox'   => true,
                    'mandatory'  => true,
                    'default'    => false,
                    'popOutList' => 'displayOptions',
                ],
                // Class Results, display_assessment_grade (global config: display_grade_graph)
                'displayAssessmentGrade'  => [
                    'caption'    => 'Display Assessment Graph',
                    'tooltip'    => 'If toggled to \'ON\', the box plot graph will be displayed on a student\'s subject grades page.',
                    'checkbox'   => true,
                    'mandatory'  => true,
                    'default'    => false,
                    'popOutList' => 'displayOptions',
                ],
                // Learning Levels tab, display_rubric_grade (global config: enable_grades_criteria)
                'displayRubricGrade'      => [
                    'caption'    => 'Display Learning Levels',
                    'tooltip'    => 'If toggled to \'ON\', assessed Capabilities and Indicators will be displayed on subject grades pages.',
                    'checkbox'   => true,
                    'mandatory'  => true,
                    'default'    => false,
                    'popOutList' => 'displayOptions',
                ],
                // Capability Marks tab, display_capability_grade (global config: grade_capability_display)
                'displayCapabilityGrade'  => [
                    'caption'    => 'Display Capability Marks',
                    'tooltip'    => 'If toggled to \'ON\', attainment for each rubric capability will be displayed on the subject grades pages.',
                    'checkbox'   => true,
                    'mandatory'  => true,
                    'default'    => false,
                    'popOutList' => 'displayOptions',
                ],
                // Progression tab, display_progression_grade (global config: grade_progression_display)
                'displayProgressionGrade' => [
                    'caption'    => 'Display Progression Graph',
                    'tooltip'    => 'If toggled to \'ON\', a line graph of student progress over time will be displayed on the grades page.',
                    'checkbox'   => true,
                    'mandatory'  => true,
                    'default'    => false,
                    'popOutList' => 'displayOptions',
                ],
                'showRubricPoints' => [
                    'caption'    => 'Show Points on Rubrics',
                    'tooltip'    => 'If toggled to \'ON\', rubrics will display the number of points associated and attained for each level of a capability.',
                    'checkbox'   => true,
                    'mandatory'  => true,
                    'default'    => false,
                    'popOutList' => 'displayOptions',
                ],
                'lmsCohortComparison' => [
                    'caption'    => 'Enable Cohort Comparison',
                    'tooltip'    => 'If toggled \'ON\', the graphs in the Grades page will use marks from the entire cohort instead of just the class.',
                    'checkbox'   => true,
                    'mandatory'  => true,
                    'default'    => false,
                    'popOutList' => 'displayOptions',
                ],
                'learningMomentsEnabled' => [
                    'caption'    => 'Learning Moments Enabled',
                    'tooltip'    => 'If toggled \'ON\', learning moments will be enabled for the students in this year level.',
                    'checkbox'   => true,
                    'mandatory'  => true,
                    'default'    => false,
                    'popOutList' => 'displayOptions',
                ],
            ],
        ];
        if ($this->getConfigLoader()->get(ConfigMapper::NAME_QR_LOGIN_ENABLED)) {
            $return['year-levels']['fields']['qrcodeEnabled'] =
                [
                    'caption'    => 'QR Code Generation Enabled',
                    'tooltip'    => 'If toggled to \'ON\', QR Code generation will be enabled for the students in this year level.',
                    'checkbox'   => true,
                    'mandatory'  => true,
                    'default'    => false,
                    'popOutList' => 'displayOptions',
                ];
        }

        $return['evidence-tag'] = [
            'title'             => 'Learning Moments',
            'mapper'            => LearningMomentEvidenceTag::class,
            'delete'            => true,
            'showId'            => false,
            'fields'            => [
                'id' => [
                    'caption'   => 'ID',
                    'width'     => 1,
                    'readonly'  => true,
                ],
                'name'          => [
                    'caption'   => 'Name',
                    'width'     => 11,
                    'maxLength' => 60,
                    'mandatory' => true,
                    'default'   => '',
                ],
            ]
        ];

        $return['achievement-category'] = [
            'title'             => 'Badge Categories',
            'mapper'            => \Schoolbox\Learning\Badge\Category\Mapper::class,
            'delete'            => true,
            'showId'            => false,
            'fields'            => [
                'id' => [
                    'caption'   => 'ID',
                    'width'     => 1,
                    'readonly'  => true,
                ],
                'name'          => [
                    'caption'   => 'Name',
                    'width'     => 11,
                    'maxLength' => 60,
                    'mandatory' => true,
                    'default'   => '',
                ],
            ]
        ];

        $return['user-title'] = [
            'title'             => 'User Titles',
            'mapper'            => \Schoolbox\User\Title\Mapper::class,
            'delete'            => true,
            'showId'            => false,
            'fields'            => [
                'id' => [
                    'caption'   => 'ID',
                    'width'     => 1,
                    'readonly'  => true,
                ],
                'title'         => [
                    'caption'   => 'Name',
                    'width'     => 11,
                    'maxLength' => 20,
                    'default'   => '',
                ],
            ]
        ];
        $return['user-pronoun'] = [
            'title'             => 'User Pronouns',
            'mapper'            => \Schoolbox\User\Pronoun\Mapper::class,
            'delete'            => true,
            'showId'            => true,
            'fields'            => [
                'id' => [
                    'caption'   => 'ID',
                    'width'     => 3,
                    'readonly'  => true,
                ],
                'subjective' => [
                    'caption'   => 'Subjective (They)',
                    'width'     => 3,
                    'maxLength' => 250,
                    'default'   => '',
                    'tooltip'    => 'The subjective pronoun used to identify the person. For example: he, she, or they.',
                ],
                'objective' => [
                    'caption'   => 'Objective (Them)',
                    'width'     => 3,
                    'maxLength' => 250,
                    'default'   => '',
                    'tooltip'    => 'The objective pronoun used to identify the person. For example: him, her, them.',
                ],
                'possessive' => [
                    'caption'   => 'Possessive (Theirs)',
                    'width'     => 3,
                    'maxLength' => 250,
                    'default'   => '',
                    'tooltip'    => 'The possessive pronoun used to identify something belonging to the person. For example: his, hers, theirs.',
                ]
            ]
        ];

        return $return;
    }

    /**
     * Show options
     *
     * @param string|null $table Arbitrary value to identify a table. Does not need to be the actual table name.
     * @throws ReflectionException|Error|RouteNotFound
     * @since 160
     * @api   /adminv2/lists
     */
    public function getIndex(?string $table): void
    {
        $this->getLists($table, false);
    }

    /**
     * @param string|null $table
     * @return void
     * @throws RouteNotFound
     * @throws ReflectionException|Error
     */
    public function getNewsIndex(?string $table): void
    {
        $this->getLists($table, false);
    }

    /**
     * Show options in JSON format
     *
     * @param string|null $table Arbitrary value to identify a table. Does not need to be the actual table name.
     * @throws RouteNotFound
     * @throws ReflectionException|Error
     * @api   /adminv2/lists/data
     *
     * @since 210
     *
     */
    public function getData(?string $table): void
    {
        $this->getLists($table, true);
    }

    /**
     * @param string|null $table
     * @return void
     * @throws RouteNotFound
     * @throws ReflectionException
     * @throws Error
     */
    public function getNewsData(?string $table): void
    {
        $this->getLists($table, true);
    }

    /**
     * Show options
     *
     * @param string|null $table Arbitrary value to identify a table. Does not need to be the actual table name.
     * @param bool        $json
     * @throws RouteNotFound
     * @throws ReflectionException|Error
     */
    private function getLists(?string $table, bool $json): void
    {
        if (empty($table)) {
            $table = "roles";
        }

        $tables = $this->getTableData();

        // Check for incorrect URLs
        if (!isset($tables[$table])) {
            $this->response = $this->responseEmpty(404);
            return;
        }

        /** @var Mapper $mapper */
        $mapper     = new $tables[$table]['mapper']();
        $model_name = $mapper->getModel();
        $prototype  = new $model_name();

        $reflect    = new ReflectionClass($prototype);
        $properties = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);

        $records = [];

        // Go through every row in the current lookup table

        if (isset($tables[$table]['findAllMethod'])) {
            $finder = $tables[$table]['findAllMethod'];
            $rows   = $mapper->$finder();
        } elseif (isset($tables[$table]['sort'])) {
            $rows = $mapper->findAll(['order' => [$tables[$table]['sort'] => 'ASC']]);
        } elseif ($prototype instanceof Sequential) {
            // Sequential: just use whatever ordering is defined in getDefaultOrderByParams
            $rows                       = $mapper->findAll();
            $tables[$table]['sortable'] = true;
        } else {
            $orderArr = [$mapper->getIdentifierColumn() => 'ASC'];
            $rows     = $mapper->findAll(['order' => $orderArr]);
        }

        foreach ($rows as $row) {
            // Go through every property available

            foreach ($properties as $prop) {
                // We only want to display fields that are listed in the 'fields' array

                if (isset($tables[$table]['fields'][$prop->getName()])) {
                    // And they have to have a getId() column (or a manually defined getter function for the column)

                    if ($reflect->hasMethod('getId') || isset($tables[$table]['fields']['id']['getter'])) {
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
                $tableCurrent['fields']         = [];
                $tableCurrent['fields']['name'] = [
                    'caption' => 'Name',
                ];
            }
        }

        $listCategory = match ($table) {
            'news-topic' => 'News',
            'achievement-category' => 'Badge',
            default => '',
        };

        if ($json) {
            $currentTable = $tables[$table];
            $this->response = $this->responseAsJson(
                [
                    'hasDBid'       => $currentTable['showId'] ?? false,
                    'canDelete'     => $currentTable['delete'] ?? false,
                    'deleteFailMsg' => $currentTable['deleteFailMsg'] ?? '',
                    'deleteRoute'   => str_replace('{table}', $table, $this->routeGenerator->generate("adminLists.post{$listCategory}Delete")),
                    'deleteWarning' => $currentTable['deleteWarning'] ?? '',
                    'fields'        => $currentTable['fields'] ? $this->reformatForJson($currentTable['fields'], false) : [],
                    'isSortable'    => $currentTable['sortable'] ?? false,
                    'noNewRecords'  => $currentTable['noNewRecords'] ?? false,
                    'rows'          => $this->reformatForJson($records, true),
                    'saveRoute'     => str_replace('{table}', $table, $this->routeGenerator->generate("adminLists.post{$listCategory}Save")),
                    'title'         => $currentTable['title'],
                ]
            );
        } else {
            $routeUrl = str_replace('{table}', $table, $this->routeGenerator->generate("adminLists.get{$listCategory}Index"));
            $this->response = $this->responseFromTemplate(
                '@adminLists/index.twig',
                [
                    'pageCurrent'    => $routeUrl,
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($routeUrl),
                    'listCategory'   => strtolower($listCategory),
                    'tables'         => $tables,
                    'table'          => $table,
                    'records'        => $records,
                ],
            );
        }
    }

    /**
     * @param array $rows
     * @param bool  $addSequence
     * @return array
     */
    private function reformatForJson(array $rows, bool $addSequence): array
    {
        $reordered = [];
        $sequence  = 1;
        foreach ($rows as $key => $array) {
            $array['id'] = $key;
            if ($addSequence) {
                $array['sequence'] = $sequence++;
            }
            $reordered[] = $array;
        }
        return $reordered;
    }
}
