<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(
        private \Schoolbox\Admin\Profile\Editor\Field\Mapper $fieldMapper,
        private \Schoolbox\CampusMapper $campusMapper,
    ) {
    }


    /**
     * Edit the layout of each profile page's fields, buttons, etc.
     */
    public function getIndex(): void
    {
        /* Figure out which roletype to display. By default, it loads teh Student
         * roletype as it tends to be the most heavily configured.
         */
        $roleType = (string)$this->requestQuery->get('roleType');

        /* Check that $roletype was valid; if not, default to Student
         */
        if (!\in_array($roleType, \Schoolbox\Role::getRoleTypes(), true)) {
            $roleType = \Schoolbox\Role::STUDENT;
        }

        $payload = (new Payload(
            $this->fieldMapper,
            $this->campusMapper
        ))->generateAll();

        $this->response = $this->responseFromTemplate('@adminProfileEditor/index.twig', [
            'pageCurrent' => $this->routeGenerator->generate('adminProfileEditor.getIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->routeGenerator->generate('adminProfileEditor.getIndex')),
            'payload' => $payload
        ], 'Profile Editor - ' . ucwords($roleType));
    }


    /**
     * POST Enable a field so it is visible on a role type's profile screen
     *
     * @api   POST /adminv2/profileEditor/field/{roleType}/{fieldId}/add
     * @since 24.1
     *
     * @param string $roleType The relevant role type
     * @param string $fieldId  The field to enable
     *
     * @return void
     */
    public function postFieldAdd(string $roleType, string $fieldId): void
    {
        try {
            $fieldModel = $this->fieldMapper->findOneByColumnList([
                'roleType' => $roleType,
                'field' => $fieldId,
            ]);

            /** @var int|null $index - The index at which to insert the field */
            $index = $this->requestPost->get('index');
            if ($index === null) {
                throw new \Exception("No index provided");
            }

            // TODO: Resequence the others.

            $fieldModel->setEnabled(1);
            $fieldModel->setSequence($index);
            $this->fieldMapper->save($fieldModel);

            $this->response = $this->responseAsJson(['success' => true]);
        } catch (\Exception $e) {
            $this->response = $this->responseAsJson(
                ['success' => false, 'message' => $e->getMessage()]
            )->withStatus(500);
        }
    }


    /**
     * POST Remove (disable) a field so that is not visible a role type's profile screen
     *
     * @api   POST /adminv2/profileEditor/field/{roleType}/{fieldId}/remove
     * @since 24.1
     *
     * @param string $roleType The relevant role type
     * @param string $fieldId  The field to remove
     */
    public function postFieldRemove(string $roleType, string $fieldId): void
    {
        try {
            $fieldModel = $this->fieldMapper->findOneByColumnList([
                'roleType' => $roleType,
                'field' => $fieldId,
            ]);

            // TODO: Resequence.

            $fieldModel->setEnabled(0);
            $this->fieldMapper->save($fieldModel);

            $this->response = $this->responseAsJson(['success' => true]);
        } catch (\Exception $e) {
            $this->response = $this->responseAsJson(
                ['success' => false, 'message' => $e->getMessage()]
            )->withStatus(500);
        }
    }


    /**
     * POST Update field settings for a particular field
     *
     * @api   POST /adminv2/profileEditor/field/{roleType}/{fieldId}/settings
     * @since 24.1
     *
     * @param string $roleType The roletype to update
     * @param string $fieldId  The field to update
     */
    public function postFieldSettings(string $roleType, string $fieldId): void
    {
        try {
            $settings = $this->requestPost->get('fieldSettings');
            if ($settings === null) {
                throw new \Exception("No field settings provided");
            }

            $fieldModel = $this->fieldMapper->findOneByColumnList([
                'roleType' => $roleType,
                'field' => $fieldId,
            ]);

            /** @var string[] $requestedAvailabilityCampuses */
            $requestedAvailabilityCampuses = $settings['availability'] ?? [];

            /** @var string[] $requestedVisibilityRoleTypes */
            $requestedVisibilityRoleTypes = $settings['visibility'] ?? [];

            // TODO

            $this->fieldMapper->save($fieldModel);

            $this->response = $this->responseAsJson(['success' => true]);
        } catch (\Exception $e) {
            $this->response = $this->responseAsJson(
                ['success' => false, 'message' => $e->getMessage()]
            )->withStatus(500);
        }
    }

    /**
     * POST Resequence fields for a particular role type
     *
     * @api   POST /adminv2/profileEditor/field/{roleType}/sequence
     * @since 24.1
     *
     * @param string $roleType The roletype for which the sequence is updated
     */
    public function postFieldSequence(string $roleType): void
    {
        try {
            /** @var string[]|null $sequence - The new sequence of field IDs, in order */
            $sequence = $this->requestPost->get('sequence', null);
            if ($sequence === null || !\is_array($sequence)) {
                throw new \Exception("Invalid sequence provided");
            }

            foreach ($sequence as $index => $fieldId) {
                $fieldModel = $this->fieldMapper->findOneByColumnList([
                    'roleType' => $roleType,
                    'field' => $fieldId,
                ]);

                $fieldModel->setSequence($index);
                $this->fieldMapper->save($fieldModel);
            }

            $this->response = $this->responseAsJson(['success' => true]);
        } catch (\Exception $e) {
            $this->response = $this->responseAsJson(
                ['success' => false, 'message' => $e->getMessage()]
            )->withStatus(500);
        }
    }
}
