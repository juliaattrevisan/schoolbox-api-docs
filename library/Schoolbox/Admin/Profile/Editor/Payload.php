<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor;

use Schoolbox\Admin\Profile\Editor\Field\Mapper;
use Schoolbox\CampusMapper;
use Schoolbox\Role;

class Payload
{
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
     * @param Mapper $fieldMapper
     * @param CampusMapper $campusMapper
     */
    public function __construct(
        private \Schoolbox\Admin\Profile\Editor\Field\Mapper $fieldMapper,
        private \Schoolbox\CampusMapper $campusMapper
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
            'options' => []
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

        /* Start processing the roleTypes node
         */
        foreach ($roleTypes as $roleType) {
            $roleTypeSettings = [
                'roleType' => $roleType,
                'fields' => [
                    'available' => [],
                    'selected' => [],
                    'mandatory' => []
                ]
            ];

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
                    'css' => $field->getCss()
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
