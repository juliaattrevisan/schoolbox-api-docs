<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(private \Schoolbox\Admin\Profile\Editor\Field\Mapper $fieldMapper)
    {
    }


    /**
     * Edit the layout of each profile page's fields, buttons, etc.
     */
    public function getIndex(): void
    {
        /* Figure out which roletype to display. By default, it loads teh Student
         * roletype as it tends to be the most heavily configured.
         */
        $roletype = (string)$this->requestQuery->get('roletype');

        /* Check that $roletype was valid; if not, default to Student
         */
        if (!\in_array($roletype, \Schoolbox\Role::getRoleTypes(), true)) {
            $roletype = \Schoolbox\Role::STUDENT;
        }

        /* Load a set of fields for the prescribed roletype
         */
        $fields = $this->fieldMapper->findByColumn('roletype', $roletype, [
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
                    'roleType' => $roletype,
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

                /* Find the roletypes ("ids") for which this field might be visilble
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

        new \Alaress_Probe($fields, $payload);

        $this->response = $this->responseFromTemplate('@profileEditor/index.twig', [

        ], 'Profile Editor - ' . ucwords($roletype));
    }


    /**
     * This executes when the superuser adds a field to a profile screen.
     *
     * @return void
     */
    public function postFieldAdd(): void
    {
        new \Alaress_Probe('postFieldAdd', $this->requestPost->getArrayCopy());
    }


    /**
     * This executes when the superuser removes a field from a profile screen.
     *
     * @return void
     */
    public function postFieldRemove(): void
    {
        new \Alaress_Probe('postFieldRemove', $this->requestPost->getArrayCopy());
    }


    /**
     * This executes when the superuser updates availability ("Appears On") and visibility
     * ("Visible For") checkbox settings on a particular field.
     *
     * @return void
     */
    public function postFieldSettings(): void
    {
        new \Alaress_Probe('postFieldSettings', $this->requestPost->getArrayCopy());
    }


    /**
     * This executes when the superuser reorders fields.
     *
     * @return void
     */
    public function postFieldSequence(): void
    {
        new \Alaress_Probe('postFieldSequence', $this->requestPost->getArrayCopy());
    }
}
