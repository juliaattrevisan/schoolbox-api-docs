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

        foreach ($fields as $field) {
            /* Ones that you could choose
             */
            $payload['data']['roleTypes']['fields']['available'][] = [
                'field' => $field->getField(),
                'displayAs' => $field->getDisplayAs(),
            ];
        }

        /* Sort by sequence for further processing. Frontend needed this in sequential
         * order, so I guess we can just do that here with a quick reindex
         */
        $fields->sortBy('sequence', 'asc');

        foreach ($fields as $field) {
            /* Ones that were previously chosen
             */
            if ($field->getEnabled()) {
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
                    'fieldSettings' => [
                        $availabilityCampus,
                        $visibilityRoleTypes
                    ]
                ];
            }
        }

        return $payload;
    }
}
