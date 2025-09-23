<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor;

use Schoolbox\Admin\Profile\Editor\Field\Mapper as FieldMapper;
use Schoolbox\Admin\Profile\Editor\Field\Model as FieldModel;
use Schoolbox\Admin\Profile\Editor\Tab\Mapper as TabMapper;
use Schoolbox\Profile\Button\Mapper as ButtonMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Role;
use Schoolbox\IConfigLoader;
use Aura\Router\Generator;

class Payload
{
    /**
     * These roletypes - and their name formats - are what we support.
     * The GUEST roletype doesn't get a look-in.
     */
    public const CONFIG_ROLETYPE = [
        Role::STUDENT => ConfigMapper::NAME_NAME_FORMAT_STUDENT,
        Role::STAFF => ConfigMapper::NAME_NAME_FORMAT_STAFF,
        Role::PARENT => ConfigMapper::NAME_NAME_FORMAT_PARENT,
    ];


    /**
     * A special pseudo-roletype
     */
    public const ROLETYPE_SELF = 'self';


    /**
     * A special case field - it's mandatory and always appears in everyone's
     * profile screen when administering
     */
    public const FIELD_PORTRAIT_IMAGE = 'portrait';


    /**
     * Common nodes and human-readable name equivalents
     * The nodes also map to types in profileEditorTypes.ts
     */
    public const AVAILABILITY_NODE = 'availability';
    private const AVAILABILITY_NAME = 'Appears On';
    private const AVAILABILITY_TOOLTIP = 'Will appear on the selected campuses';
    public const VISIBILITY_NODE = 'visibility';
    private const VISIBILITY_NAME = 'Visible For';
    private const VISIBILITY_TOOLTIP = 'Will appear for profile viewers in the selected role types';
    public const ADDITIONAL_SETTINGS_NODE = 'additional';
    private const ADDITIONAL_SETTINGS_NAME = 'Additional Settings';
    private const ADDITIONAL_SETTINGS_TOOLTIP = null;
    /**
     * currently these are just a global spec of additional field settings that applies to all fields,
     * individual fields then opt-in to having these settings available based on
     * if the given id exists inside the field's "options" JSON column
     *
     * Ideally at some point an individual field could define its own additional settings
     * completely and we wouldn't need this here
     */
    public const ADDITIONAL_SETTINGS_OPTIONS = [
        [
            'type' => 'checkbox',
            'id' => 'clickToReveal',
            'name' => 'Activate View More',
            'meta' => 'Only show content after the user chooses to view it.'
        ],
        [
            'type' => 'text',
            'id' => 'iframeLink',
            'name' => 'iFrame Link',
            'meta' => "
                The URL of a webpage to embed, surround with remote_service(https://...) to integrate with the Remote Services API.
                Valid tokens: [username], [external_id], [email]
            ",
        ],
        [
            'type' => 'number',
            'id' => 'iframeHeight',
            'name' => 'Height',
            'meta' => 'Height of the iframe in pixels',
        ],
    ];

    /**
     * The labels of the field categories which are used to group the fields in
     * the available fields palette on the profile editor screen
     * Currently just "Internal Fields" and "External Fields"
     */
    private const FIELD_CATEGORY_INTERNAL = 'Internal Fields';
    private const FIELD_CATEGORY_EXTERNAL = 'External Fields';


    /**
     * Additional meta for each setting option that can be chosen
     * to provide more context to the user for what they're actually choosing.
     * The meta can change depending on the selected role type tab, so we need to have each separately.
     *
     * This is a nested array of the form:
     *  [The relevent settings key], e.g. self::VISIBILITY_NODE or self::AVAILABILITY_NODE
     *    ->[The option id to which the meta applies, e.g. self::ROLETYPE_SELF]
     *      ->[The role type (tab) for which the meta applies, e.g. Role::STUDENT]
     *
     * If the meta is not present for a given option, it will be null.
     */
    public const FIELD_SETTINGS_META = [
        /* A set of (viewer => viewee) metadata tuples
         */
        self::VISIBILITY_NODE => [
            self::ROLETYPE_SELF => [
                Role::STUDENT => "The user themself",
                Role::STAFF => "The user themself",
                Role::PARENT => "The user themself",
            ],
            Role::STUDENT => [
                Role::STUDENT => "All users with the 'student' role type",
                Role::STAFF => "All users with the 'student' role type",
                Role::PARENT => "All users with the 'student' role type",
            ],
            Role::STAFF => [
                Role::STUDENT => "All users with the 'staff' role type who have 'View Student Profile' access",
                Role::STAFF => "All users with the 'staff' role type",
                Role::PARENT => "All users with the 'staff' role type who have 'View Parent Profile' access",
            ],
            Role::PARENT => [
                Role::STUDENT => "Parents who are linked to the student",
                Role::STAFF => "All users with the 'parent' role type",
                Role::PARENT => "All users with the 'parent' role type",
            ]
        ]
    ];


    /**
     * @param IConfigLoader $config
     * @param Generator $routeGenerator
     * @param FieldMapper $fieldMapper
     * @param CampusMapper $campusMapper
     * @param ButtonMapper $buttonMapper
     */
    public function __construct(
        private IConfigLoader $config,
        private Generator $routeGenerator,
        private FieldMapper $fieldMapper,
        private TabMapper $tabMapper,
        private CampusMapper $campusMapper,
        private ButtonMapper $buttonMapper
    ) {
    }

    /**
     * Generates a payload for rendering a list of fields for the super to choose,
     * and also a list of ones they've previously chosen,
     * to appear on the given $roleType's profile screen

     ** @return array
     */
    public function generateAll(): array
    {
        $roleTypes = [
            Role::STUDENT,
            Role::STAFF,
            Role::PARENT,
        ];

        /* Put it into the frontend payload format
         */
        $payload = [
            'data' => [
                'fieldSettings' => [
                    self::AVAILABILITY_NODE => $this->getSettingsForAvailabilityNode(),
                    self::VISIBILITY_NODE => $this->getSettingsForVisibilityNode($roleTypes),
                    self::ADDITIONAL_SETTINGS_NODE => $this->getSettingsForAdditionalSettingsNode(),
                ],
                'tabSettings' => [
                    self::AVAILABILITY_NODE => $this->getSettingsForAvailabilityNode(),
                    self::VISIBILITY_NODE => $this->getSettingsForVisibilityNode([Role::STAFF, Role::PARENT,])
                ],
                'roleTypes' => [],
            ]
        ];

        /* Start processing the roleTypes node
         */
        foreach ($roleTypes as $roleType) {
            $roleTypeSettings = [
                'roleType' => $roleType,
                'nameFormat' => $this->config->get(self::CONFIG_ROLETYPE[$roleType]),
                'fields' => [
                    'available' => [],
                    'selected' => [],
                    'mandatory' => []
                ],
                'buttons' => [],
                'tabs' => [],
                '_links' => [
                    'manageButtons' => $this->routeGenerator->generate('adminProfileButton.getIndex', [
                        'roleType' => $roleType
                    ]),
                ],
                '_routes' => [
                    'postNameFormat' => $this->routeGenerator->generate('adminProfileEditor.postNameFormat', ['roleType' => $roleType]),
                    'postFieldAdd' => $this->routeGenerator->generate('adminProfileEditorField.postAdd', ['roleType' => $roleType]),
                    'postFieldRemove' => $this->routeGenerator->generate('adminProfileEditorField.postRemove', ['roleType' => $roleType]),
                    'postFieldSettings' => $this->routeGenerator->generate('adminProfileEditorField.postSettings', ['roleType' => $roleType]),
                    'postFieldSequence' => $this->routeGenerator->generate('adminProfileEditorField.postSequence', ['roleType' => $roleType]),
                    'postTabSettings' => $this->routeGenerator->generate('adminProfileEditorTab.postSettings', ['roleType' => $roleType]),
                    'postTabSequence' => $this->routeGenerator->generate('adminProfileEditorTab.postSequence', ['roleType' => $roleType]),
                ]
            ];

            /* Buttons are obtained (and managed) totally separately from Profile Fields
             */
            $buttons = $this->buttonMapper
                ->findByColumn('roletype', $roleType)
                ->sortBy('sortOrder', 'asc');

            foreach ($buttons as $button) {
                $roleTypeSettings['buttons'][] = [
                    'id' => $button->getId(),
                    'icon' => $button->getIcon(),
                    'title' => $button->getTitle(),
                    'text' => $button->getText(),
                    '_links' => [
                        'modify' => $this->routeGenerator->generate('adminProfileButton.getModify', [
                            'id' => $button->getId()
                        ]),
                    ]
                ];
            }

            /* Load a set of tabs for the prescribed roletype
             */
            $tabs = $this->tabMapper->findByColumn('roletype', $roleType, [
                'order' => [
                    'sequence' => 'asc'
                ]
            ]);

            foreach ($tabs as $tab) {
                $roleTypeSettings['tabs'][] = [
                    'id' => $tab->getTab(),
                    'label' => $tab->getDisplayAs(),
                    'tabSettings' => [
                        /* Find the ids of the campuses for which this tab might be available
                         */
                        self::AVAILABILITY_NODE => $tab->getAvailabilityCampus()?->indexByCallback(static function (\Schoolbox\Admin\Profile\Editor\Tab\Availability\Campus\Model $campus) {
                            return $campus->getCampus()?->getId();
                        })->keys(),

                        /* Find the roletypes ("ids") for which this tab might be visible
                         */
                        self::VISIBILITY_NODE => $tab->getVisibility()?->indexBy('roleType')->keys()
                    ],
                    'availableTabSettings' => [
                        self::AVAILABILITY_NODE => true,
                        self::VISIBILITY_NODE => true
                    ],
                ];
            }

            /* Load a set of fields for the prescribed roletype
             */
            $fields = $this->fieldMapper->findByColumn('roletype', $roleType, [
                'order' => [
                    'display_as' => 'asc'
                ]
            ]);

            /* Ones that you could possibly choose
             */
            foreach ($fields as $field) {
                /* Portrait Image can never be chosen; it's mandatory
                 */
                if (self::FIELD_PORTRAIT_IMAGE === $field->getField()) {
                    continue;
                }

                $availableFieldSettings = $this->getFieldSettingsAvailability($field);

                /* The default settings to set for this field when it gets selected
                 */
                $defaultFieldSettings = [
                    self::AVAILABILITY_NODE => [],
                    self::VISIBILITY_NODE => [],
                    self::ADDITIONAL_SETTINGS_NODE => $field->getOptionsDefault() ?? [],
                ];

                /* The frontend expects a {} for an empty additional settings node, php will json_encode an empty array as []
                 * so we need to convert it here
                 */
                foreach ($defaultFieldSettings as $settingKey => $settingValue) {
                    if ($settingValue instanceof \Schoolbox\Collection ? $settingValue->count() === 0 : empty($settingValue)) {
                        $defaultFieldSettings[$settingKey] = new \stdClass();
                    }
                }

                $roleTypeSettings['fields']['available'][] = [
                    'field' => $field->getField(),
                    'displayAs' => $field->getDisplayAs(),
                    'icon' => $field->getIcon(),
                    'css' => $field->getCss(),
                    'terminology' => $field->getTerm(),
                    'category' => $field->getIsExternal() ? self::FIELD_CATEGORY_EXTERNAL : self::FIELD_CATEGORY_INTERNAL,
                    'defaultFieldSettings' => $defaultFieldSettings,
                    'availableFieldSettings' => $availableFieldSettings,
                ];
            }

            /* Sort by sequence for further processing. Frontend needed this in sequential
             * order, so I guess we can just do that here with a quick reindex
             */
            $fields->sortBy('sequence', 'asc');

            /* We only care about the ones that were previously chosen (enabled == 1)
             */
            foreach ($fields as $field) {
                if (!$field->getEnabled()) {
                    continue;
                }

                $node = 'selected';

                /* Portrait Image can never be disabled; it's mandatory
                 */
                if (self::FIELD_PORTRAIT_IMAGE === $field->getField()) {
                    $node = 'mandatory';
                }

                /* All the field settings that are currently set for this field
                 */
                $fieldSettings = [
                    /* Find the ids of the campuses for which this field might be available
                     */
                    self::AVAILABILITY_NODE => $field->getAvailabilityCampus()?->indexByCallback(static function (\Schoolbox\Admin\Profile\Editor\Field\Availability\Campus\Model $campus) {
                        return $campus->getCampus()?->getId();
                    })->map(fn() => true),
                    /* Find the roletypes ("ids") for which this field might be visible
                     */
                    self::VISIBILITY_NODE => $field->getVisibility()?->indexBy('roleType')->map(fn() => true),
                    /* Find the enabled additional setting keys for this field
                     */
                    self::ADDITIONAL_SETTINGS_NODE => $field->getOptions() ?? [],
                ];

                /* The frontend expects a {} for an empty additional settings node, php will json_encode an empty array as []
                 * so we need to convert it here
                 */
                foreach ($fieldSettings as $settingKey => $settingValue) {
                    if ($settingValue instanceof \Schoolbox\Collection ? $settingValue->count() === 0 : empty($settingValue)) {
                        $fieldSettings[$settingKey] = new \stdClass();
                    }
                }

                /* Pile it into the stack
                 */
                $roleTypeSettings['fields'][$node][] = [
                    'field' => $field->getField(),
                    'displayAs' => $field->getDisplayAs(),
                    'icon' => $field->getIcon(),
                    'css' => $field->getCss(),
                    'terminology' => $field->getTerm(),
                    'fieldSettings' => $fieldSettings,
                    'availableFieldSettings' => $this->getFieldSettingsAvailability($field),
                ];
            }

            /* Pile it in
             */
            $payload['data']['roleTypes'][] = $roleTypeSettings;
        }

        return $payload;
    }

    /**
     * Generate payload data for either:
     *  - $payload['data']['fieldSettings'][self::AVAILABILITY_NODE] or
     *  - $payload['data']['tabSettings'][self::AVAILABILITY_NODE]
     *
     * @return array
     */
    private function getSettingsForAvailabilityNode(): array
    {
        $campuses = $this->campusMapper->findAll();

        /* Field settings (groups of checkboxes) that you can tick
         */
        $settings = [
            'displayAs' => self::AVAILABILITY_NAME,
            'tooltip' => self::AVAILABILITY_TOOLTIP,
            'options' => []
        ];

        foreach ($campuses as $campus) {
            $settings['options'][] = [
                'type' => 'checkbox',
                'id' => $campus->getId(),
                'name' => $campus->getName(),
                'meta' => null
            ];
        }

        return $settings;
    }

    /**
     * Generate payload data for either:
     *  - $payload['data']['fieldSettings'][self::VISIBILITY_NODE] or
     *  - $payload['data']['tabSettings'][self::VISIBILITY_NODE]
     *
     * @param array  $roleTypes
     * @return array
     */
    private function getSettingsForVisibilityNode(array $roleTypes): array
    {
        $metadata = self::FIELD_SETTINGS_META[self::VISIBILITY_NODE];

        /* Role types you can choose
         */
        $settings = [
            'displayAs' => self::VISIBILITY_NAME,
            'tooltip' => self::VISIBILITY_TOOLTIP,
            'options' => [
                [
                    'type' => 'checkbox',
                    'id' => self::ROLETYPE_SELF,
                    'name' => ucwords(self::ROLETYPE_SELF),
                    'meta' => $metadata[self::ROLETYPE_SELF]
                ]
            ]
        ];

        foreach ($roleTypes as $roleType) {
            $settings['options'][] = [
                'type' => 'checkbox',
                'id' => $roleType,
                'name' => ucwords($roleType),
                'meta' => $metadata[$roleType] ?? null
            ];
        }

        return $settings;
    }

    /**
     * Generate payload data for:
     *  - $payload['data']['fieldSettings'][self::ADDITIONAL_SETTINGS_NODE]
     *
     * @return array
     */
    private function getSettingsForAdditionalSettingsNode(): array
    {
        /* Field settings (groups of checkboxes) that you can tick
         */
        $settings = [
            'displayAs' => self::ADDITIONAL_SETTINGS_NAME,
            'tooltip' => self::ADDITIONAL_SETTINGS_TOOLTIP,
            'options' => self::ADDITIONAL_SETTINGS_OPTIONS,
        ];

        return $settings;
    }

    /**
     * Generate payload data for a specific field's settings availability:
     *  - $payload['data']['roleTypes'][*]['fields'][*][*]['availableFieldSettings']
     *
     * @param FieldModel $field
     * @return array
     */
    private function getFieldSettingsAvailability(FieldModel $field): array
    {
        $additionalSettings = new \stdClass();
        foreach ($field->getOptions() ?? [] as $key => $value) {
            $additionalSettings->{$key} = true;
        }

        $settings = [
            self::AVAILABILITY_NODE => true,
            self::VISIBILITY_NODE => true,
            self::ADDITIONAL_SETTINGS_NODE => $additionalSettings,
        ];

        return $settings;
    }
}
