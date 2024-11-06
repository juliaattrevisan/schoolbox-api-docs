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
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(
        private Field\Mapper $fieldMapper,
        private Field\Availability\Campus\Mapper $availabilityCampusMapper,
        private Field\Visibility\Mapper $visibilityMapper,
        private \Schoolbox\CampusMapper $campusMapper,
        private \Schoolbox\Config\ConfigMapper $configMapper,
        private \Schoolbox\Profile\Button\Mapper $buttonMapper
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
        if (!isset(Payload::CONFIG_ROLETYPE[$roleType])) {
            $roleType = \Schoolbox\Role::STUDENT;
        }

        /* Frontend wanted the full payload for all roletypes when we arrive at
         * the index screen, rather than have the $roleType's payload specifically.
         */
        $payload = (new Payload(
            $this->getConfigLoader(),
            $this->fieldMapper,
            $this->campusMapper
        ))->generateAll();

        /* Buttons are obtained (and managed) totally separately from Profile Fields
         * TODO @zacmcw you can probably think of a better way to do this in frontend world
         */
        $buttons = [];

        foreach (array_keys(Payload::CONFIG_ROLETYPE) as $roleType) {
            $buttons[$roleType] = $this->buttonMapper
                ->findByColumn('roletype', $roleType)
                ->sortBy('sortOrder', 'asc');
        }

        $this->response = $this->responseFromTemplate('@adminProfileEditor/index.twig', [
            'pageCurrent' => $this->routeGenerator->generate('adminProfileEditor.getIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminProfileEditor.getIndex')),
            /* Profile Fields
             */
            'payload' => $payload,
            /* Profile Buttons
             */
            'buttons' => $buttons,
        ], 'Profile Editor - ' . ucwords($roleType));
    }


    /**
     * POST Enable a field so it is visible on a role type's profile screen
     *
     * @api   POST /adminv2/profileEditor/field/{roleType}/{fieldId}/add
     * @since 24.1
     *
     * @param string $roleType The relevant role type
     * @param Field\Model $field The field to enable
     * @return void
     * @throws \InvalidArgumentException
     */
    public function postFieldAdd(string $roleType, Field\Model $field): void
    {
        try {
            /* When clicked to add, new fields are spawned at the top of the list,
             * forcing other fields down the screen.
             *
             * When dragged, new fields are spawned in the spot you dragged - this could
             * be in the middle of the list, or at either extreme. Anything equal to, or
             * greater, than this sequence index needs to be incremented to make a gap.
             *
             * This means we need to first obtain the current set of enabled fields:
             */
            $fields = $this->fieldMapper->findByColumnList([
                'roleType' => $roleType,
                'enabled' => true,
            ], [
                'order' => [
                    'sequence' => 'asc'
                ]
            ]);

            /* Then find out what sequence we need to inject a field.
             */
            /** @var int|null $index - The index at which to insert the field */
            $index = (int)($this->requestPost->get('index')) + 1;

            /* ...then increment fields whose sequences are equal to, or greater than, $index
             */
            foreach ($fields as $currentField) {
                if (Payload::FIELD_PORTRAIT_IMAGE === $currentField->getField()) {
                    /* Portrait Image is always sequence zero
                     */
                    $currentField->setSequence(0);
                } elseif ($currentField->getSequence() >= $index) {
                    /* Increment by one
                     */
                    $currentField->setSequence($currentField->getSequence() + 1);
                }

                $this->fieldMapper->save($currentField);
            }

            /* Spawn in place using the index provided by frontend
             */
            $field->setSequence($index);
            $field->setEnabled(1);

            $this->fieldMapper->save($field);

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
     * @param Field\Model $field The field to remove
     * @throws \InvalidArgumentException
     */
    public function postFieldRemove(string $roleType, Field\Model $field): void
    {
        try {
            $field->setEnabled(0);
            $this->fieldMapper->save($field);

            /* I think that we should remove the Availability and Visibility settings
             * if you disable a field. Disabling and immediately re-adding it will
             * spawn the field with checkboxes unticked; but if you refresh the page
             * it'll restore the values persisted in the database, resulting in a weird
             * kind of outcome you might not have expected.
             *
             * If you're removing a field, I have to assume you're doing that for a reason
             * that doesn't warrant restoring configuration from the grave
             */
            $this->availabilityCampusMapper->deleteByColumn('profile_field_id', $field->getId());
            $this->visibilityMapper->deleteByColumn('profile_field_id', $field->getId());

            /* Removed fields will leave a hole in the sequencing.
             * To make sequencing contiguous, we need to fetch all
             * the fields ordered by their sequence:
             */
            $fields = $this->fieldMapper->findByColumnList([
                'roleType' => $roleType,
                'enabled' => true,
            ], [
                'order' => [
                    'sequence' => 'asc'
                ]
            ]);

            /* ...then reassign them in 1-indexed order.
             */
            $i = 1;

            foreach ($fields as $currentField) {
                if (Payload::FIELD_PORTRAIT_IMAGE === $currentField->getField()) {
                    /* Portrait Image is always sequence zero
                     */
                    $field->setSequence(0);
                } else {
                    /* Assign new sequence
                     */
                    $field->setSequence($i++);
                }

                $this->fieldMapper->save($currentField);
            }

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
     * @param Field\Model $field  The field to update
     * @throws \InvalidArgumentException
     */
    public function postFieldSettings(string $roleType, Field\Model $field): void
    {
        try {
            $settings = $this->requestPost->get('fieldSettings');

            if (null === $settings) {
                throw new \RuntimeException('No field settings provided');
            }

            /* The updated availabilities and visibilities are in this array struct
             * where availability is *presumed* to be campus ids only (for now)
             * and visibility is *presumed* to be roletype strings only (for now)
             * imho it'd be better served seeing a campus node in here and also a
             * roletype node, so you know conclusively what these represent.
             *
                [fieldSettings] => Array
                    (
                        [availability] => Array
                            (
                                [0] => 1
                                [1] => 2
                            )

                        [visibility] => Array
                            (
                                [0] => parent
                                [1] => student
                                [2] => staff
                                [3] => self
                            )

                    )
             */

            /* First purge existing relations
             */
            $this->availabilityCampusMapper->deleteByColumn('profile_field_id', $field->getId());

            /* Then, if there are any campus ids chosen, reinsert these new ones
             * Otherwise leave it empty (this is a legitimate use case)
             */
            /** @var int[] $newCampusIds */
            $newCampusIds = $settings['availability'] ?? [];

            if (\count($newCampusIds)) {
                $campuses = $this->campusMapper->findByColumn('id', $newCampusIds);

                foreach ($campuses as $campus) {
                    $relation = $this->availabilityCampusMapper->createModel()
                        ->setField($field)
                        ->setCampus($campus);

                    $this->availabilityCampusMapper->save($relation);
                }
            }

            /* First purge existing relations
             */
            $this->visibilityMapper->deleteByColumn('profile_field_id', $field->getId());

            /* Then, if there are any roletypes chosen, reinsert these new ones
             * Otherwise leave it empty (this is a legitimate use case)
             */
            /** @var string[] $newRoleTypes */
            $newRoleTypes = $settings['visibility'] ?? [];

            if (\count($newRoleTypes)) {
                foreach ($newRoleTypes as $newRoleType) {
                    $relation = $this->visibilityMapper->createModel()
                        ->setField($field)
                        ->setRoleType($newRoleType);

                    $this->visibilityMapper->save($relation);
                }
            }

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
     * Note that sequences are 1-indexed.
     *
     * @api   POST /adminv2/profileEditor/field/{roleType}/sequence
     * @since 24.1
     *
     * @param string $roleType The roletype for which the sequence is updated
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function postFieldSequence(string $roleType): void
    {
        try {
            /** @var string[]|null $sequence - The new sequence of field IDs, in order */
            $sequence = $this->requestPost->get('sequence');

            if (!\is_array($sequence)) {
                throw new \RuntimeException('Invalid sequence provided');
            }

            /* When we resequence fields, we aren't adding or removing them
             * which means we can presume to obtain all the necessary fields
             * in one database call
             */
            $fields = $this->fieldMapper->findByColumnList([
                'roleType' => $roleType,
                'enabled' => true,
            ])->indexBy('field');

            /* Sequence is actually a (int, string) tuple that represents
             * (index, field name), not its primary key in profile_field.
             */
            foreach ($sequence as $index => $field) {
                $fieldModel = $fields->getOffset($field);

                /* For some edge-casey reason, the model doesn't exist anymore;
                 * someone else might have deleted it at the same moment we were
                 * trying to resequence it
                 */
                if (null === $fieldModel) {
                    continue;
                }

                /* Portrait Image should never appear in this $sequence array
                 * But if it does one day, is always sequence zero
                 *
                 * if (Payload::FIELD_PORTRAIT_IMAGE === $field) {
                 *     $fieldModel->setSequence(0);
                 * }
                 */
                $fieldModel->setSequence($index + 1);

                $this->fieldMapper->save($fieldModel);
            }

            $this->response = $this->responseAsJson(['success' => true]);
        } catch (\Exception $e) {
            $this->response = $this->responseAsJson(
                ['success' => false, 'message' => $e->getMessage()]
            )->withStatus(500);
        }
    }


    /**
     * Update the name format for users' names on their profile screens.
     *
     * This only applies to student roletype for now, because of EIM's custom work,
     * but it could potentially spread to other roletypes once people get wind of it.
     *
     * @param string $roleType
     * @return void
     * @throws \PDOException
     * @throws Exception
     * @throws Deleted
     * @throws \UnexpectedValueException
     */
    public function postNameFormat(string $roleType): void
    {
        try {
            /* Figure out which roletype config to update
             */
            $configName = Payload::CONFIG_ROLETYPE[$roleType];
            $nameFormat = $this->requestPost->get('nameFormat');

            /* No format? No trabajo; exit early
             */
            if (null === $nameFormat) {
                return;
            }

            $config = $this->configMapper->findOneByColumn('name', $configName)
                ->setValue($nameFormat);

            $this->configMapper->save($config);

            $this->response = $this->responseAsJson(['success' => true]);
        } catch (\Exception $e) {
            $this->response = $this->responseAsJson(
                ['success' => false, 'message' => $e->getMessage()]
            )->withStatus(500);
        }
    }
}
