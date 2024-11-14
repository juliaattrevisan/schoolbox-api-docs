<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor;

use Schoolbox\Admin\Profile\Editor\Field\Mapper as FieldMapper;
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
     */
    private const AVAILABILITY_NODE = 'availability';
    private const AVAILABILITY_NAME = 'Availability';
    private const AVAILABILITY_TOOLTIP = 'Will appear on the selected campuses';
    private const VISIBILITY_NODE = 'visibility';
    private const VISIBILITY_NAME = 'Visibility';
    private const VISIBILITY_TOOLTIP = 'Will appear for profile viewers in the selected role types';

    /**
     * The labels of the field categories which are used to group the fields in
     * the available fields palette on the profile editor screen
     * Currently just "Internal Fields" and "External Fields"
     */
    private const FIELD_CATEGORY_INTERNAL = 'Internal Fields';
    private const FIELD_CATEGORY_EXTERNAL = 'External Fields';


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
     * Make a payload containing data for all the roletypes we support
     *
     * @return array|array[]
     */
    public function generateAll(): array
    {
        return $this->generate([
            Role::STUDENT,
            Role::STAFF,
            Role::PARENT,
        ]);
    }


    /**
     * Generates a payload for rendering a list of fields for the super to choose,
     * and also a list of ones they've previously chosen,
     * to appear on the given $roleType's profile screen
     *
     * $roleTypes lets you specify the roletype whose payloads you want to
     * construct: you can specify all of them, or a one-element array, etc
     *
     * @param array $roleTypes
     * @return array
     */
    public function generate(array $roleTypes): array
    {
        /* Exit early when you didn't specify any roletypes by accident
         */
        if (!\count($roleTypes)) {
            return [];
        }

        /* Put it into the frontend payload format
         */
        $payload = [
            'data' => [
                'fieldSettings' => [],
                'tabSettings' => [],
                'roleTypes' => [],
            ]
        ];

        /* Field settings (groups of checkboxes) that you can tick
         *
         * Find the campuses you can choose
         */
        $campuses = $this->campusMapper->findAll();

        /* Find available campuses
         */
        $fieldSettingsCampus = [
            'displayAs' => self::AVAILABILITY_NAME,
            'tooltip' => self::AVAILABILITY_TOOLTIP,
            'options' => []
        ];

        foreach ($campuses as $campus) {
            $fieldSettingsCampus['options'][] = [
                'type' => 'campus',
                'id' => $campus->getId(),
                'name' => $campus->getName()
            ];
        }

        /* Find the roletypes you can choose
         */
        $fieldSettingsRoleType = [
            'displayAs' => self::VISIBILITY_NAME,
            'tooltip' => self::VISIBILITY_TOOLTIP,
            'options' => [
                [
                    'type' => 'roleType',
                    'id' => self::ROLETYPE_SELF,
                    'name' => ucwords(self::ROLETYPE_SELF)
                ]
            ]
        ];

        foreach ($roleTypes as $roleType) {
            $fieldSettingsRoleType['options'][] = [
                'type' => 'roleType',
                'id' => $roleType,
                'name' => ucWords($roleType)
            ];
        }

        $payload['data']['fieldSettings'] = [
            self::AVAILABILITY_NODE => $fieldSettingsCampus,
            self::VISIBILITY_NODE => $fieldSettingsRoleType
        ];

        // tabSettings is currently identical to fieldSettings but this allows it to diverge in the future
        $payload['data']['tabSettings'] = $payload['data']['fieldSettings'];

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
                    ]
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

                $roleTypeSettings['fields']['available'][] = [
                    'field' => $field->getField(),
                    'displayAs' => $field->getDisplayAs(),
                    'icon' => $field->getIcon(),
                    'css' => $field->getCss(),
                    'terminology' => $field->getTerm(),
                    'category' => $field->getIsExternal() ? self::FIELD_CATEGORY_EXTERNAL : self::FIELD_CATEGORY_INTERNAL,
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

                /* Pile it into the stack
                 */
                $roleTypeSettings['fields'][$node][] = [
                    'field' => $field->getField(),
                    'displayAs' => $field->getDisplayAs(),
                    'icon' => $field->getIcon(),
                    'css' => $field->getCss(),
                    'terminology' => $field->getTerm(),
                    'fieldSettings' => [
                        /* Find the ids of the campuses for which this field might be available
                         */
                        self::AVAILABILITY_NODE => $field->getAvailabilityCampus()?->indexByCallback(static function (\Schoolbox\Admin\Profile\Editor\Field\Availability\Campus\Model $campus) {
                            return $campus->getCampus()?->getId();
                        })->keys(),
                        /* Find the roletypes ("ids") for which this field might be visible
                         */
                        self::VISIBILITY_NODE => $field->getVisibility()?->indexBy('roleType')->keys()
                    ]
                ];
            }

            /* Pile it in
             */
            $payload['data']['roleTypes'][] = $roleTypeSettings;
        }

        return $payload;
    }
}
