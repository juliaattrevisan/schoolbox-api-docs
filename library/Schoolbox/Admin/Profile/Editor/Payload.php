<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor;

use DebugBar\DebugBarException;
use Schoolbox\Admin\Profile\Editor\Field\Mapper;
use Schoolbox\CampusMapper;
use Schoolbox\Role;

class Payload
{
    /**
     * Common nodes and human-readable name equivalents
     */
    private const AVAILABILITY_NODE = 'availability';
    private const AVAILABILITY_NAME = 'Availability';
    private const VISIBILITY_NODE = 'visibility';
    private const VISIBILITY_NAME = 'Visibility';


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
     * Generates a payload for rendering a list of fields for the super to choose,
     * and also a list of ones they've previously chosen,
     * to appear on the given $roleType's profile screen
     *
     * @param string $roleType
     * @return array
     * @throws DebugBarException
     */
    public function generate(string $roleType): array
    {
        /* Load a set of fields for the prescribed roletype
         */
        $fields = $this->fieldMapper->findByColumn('roletype', $roleType, [
            'order' => [
                'display_as' => 'asc'
            ]
        ]);

        /* Put it into the frontend payload format
         */
        $payload = [
            'data' => [
                'fieldSettings' => [],
                'roleTypes' => [
                    'roleType' => $roleType,
                    'fields' => [
                        'available' => [],
                        'selected' => []
                    ]
                ],
            ]
        ];

        /* Field settings (groups of checkboxes) that you can tick
         *
         * Find the campuses you can choose
         */
        $campuses = $this->campusMapper->findAll();

        $fieldSettingsCampus = [
            'key' => self::AVAILABILITY_NODE,
            'displayAs' => self::AVAILABILITY_NAME,
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
            'key' => self::VISIBILITY_NODE,
            'displayAs' => self::VISIBILITY_NAME,
            'options' => [
                [
                    'type' => 'roleType',
                    'id' => Role::STUDENT,
                    'name' => ucwords(Role::STUDENT),
                ],
                [
                    'type' => 'roleType',
                    'id' => Role::STAFF,
                    'name' => ucwords(Role::STAFF),
                ],
                [
                    'type' => 'roleType',
                    'id' => Role::PARENT,
                    'name' => ucwords(Role::PARENT),
                ]
            ]
        ];

        $payload['data']['fieldSettings'] = [
            $fieldSettingsCampus,
            $fieldSettingsRoleType
        ];

        /* Ones that you could possibly choose
         */
        foreach ($fields as $field) {
            $payload['data']['roleTypes']['fields']['available'][] = [
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

            /* Find the ids of the campuses for which this field might be available
             */
            $availabilityCampusIds = [];

            foreach ($field->getAvailabilityCampus() as $availabilityCampus) {
                $availabilityCampusIds[] = $availabilityCampus->getCampus()->getId();
            }

            $availabilityCampus = [
                'key' => self::AVAILABILITY_NODE,
                'id' => $availabilityCampusIds
            ];

            /* Find the roletypes ("ids") for which this field might be visible
             */
            $visibilityRoleTypesIds = [];

            foreach ($field->getVisibility() as $visibility) {
                $visibilityRoleTypesIds[] = $visibility->getRoleType();
            }

            $visibilityRoleTypes = [
                'key' => self::VISIBILITY_NODE,
                'id' => $visibilityRoleTypesIds
            ];

            /* Pile it into the stack
             */
            $payload['data']['roleTypes']['fields']['selected'][] = [
                'field' => $field->getField(),
                'displayAs' => $field->getDisplayAs(),
                'icon' => $field->getIcon(),
                'css' => $field->getCss(),
                'fieldSettings' => [
                    $availabilityCampus,
                    $visibilityRoleTypes
                ]
            ];
        }

        return $payload;
    }
}
