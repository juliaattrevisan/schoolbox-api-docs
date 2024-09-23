<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor;

class Payload
{
    /**
     * @param Field\Mapper $fieldMapper
     */
    public function __construct(private \Schoolbox\Admin\Profile\Editor\Field\Mapper $fieldMapper)
    {
    }


    /**
     * Generates a payload for rendering a list of fields for the super to choose,
     * and also a list of ones they've previously chosen,
     * to appear on the given $roleType's profile screen
     *
     * @param string $roleType
     * @return array
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
                'key' => 'appearsOn',
                'id' => $availabilityCampusIds
            ];

            /* Find the roletypes ("ids") for which this field might be visible
             */
            $visibilityRoleTypesIds = [];

            foreach ($field->getVisibility() as $visibility) {
                $visibilityRoleTypesIds[] = $visibility->getRoleType();
            }

            $visibilityRoleTypes = [
                'key' => 'visibleFor',
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
