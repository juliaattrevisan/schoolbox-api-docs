<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Field;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Profile\Editor\Payload;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(
        private Mapper $fieldMapper,
        private Availability\Campus\Mapper $availabilityCampusMapper,
        private Visibility\Mapper $visibilityMapper,
        private \Schoolbox\CampusMapper $campusMapper
    ) {
    }

    /**
     * Re-assigns sequences to all the fields if they have changed
     * @param Model[] $fields
     */
    private function saveSequence(array $fields): void
    {
        /* Portrait Image must always be sequence 0, so start at 1 */
        $i = 1;
        foreach ($fields as $field) {
            $initialSequence = $field->getSequence();

            if (Payload::FIELD_PORTRAIT_IMAGE === $field->getField()) {
                $field->setSequence(0);
            } else {
                $field->setSequence($i++);
            }

            if ($field->getSequence() === $initialSequence) {
                /* No need to save, as the sequence hasn't changed */
                continue;
            }

            $this->fieldMapper->save($field);
        }
    }

    /**
     * POST Enable a field so it is visible on a role type's profile screen
     *
     * @api   POST /adminv2/profileEditor/field/{roleType}/{fieldId}/add
     * @since 24.1
     *
     * @param string $roleType The relevant role type
     * @param Model $field The field to enable
     * @return void
     * @throws \InvalidArgumentException
     */
    public function postAdd(string $roleType, Model $field): void
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

            /* Avoid adding the same field twice */
            $fields->filter(fn(Model $f): bool => $f->getField() === $field->getField());

            /* Then find out what sequence we need to inject a field
             * and add it to the stack in the right spot
             */
            /** @var int|null $index - The index at which to insert the field */
            $index = (int)($this->requestPost->get('index')) + 1;

            $stack = $fields->getAll();
            array_splice($stack, $index, 0, [$field]);

            /* Temporarily set the sequence of the field to -1 to force it to save when resequenced
             */
            $field->setSequence(-1);
            $field->setEnabled(1);

            $this->saveSequence($stack);

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
     * @param Model $field The field to remove
     * @throws \InvalidArgumentException
     */
    public function postRemove(string $roleType, Model $field): void
    {
        try {
            $field->setEnabled(0);
            $field->setSequence(0);
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

            $this->saveSequence($fields->getAll());

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
     * @param Model $field  The field to update
     * @throws \InvalidArgumentException
     */
    public function postSettings(string $roleType, Model $field): void
    {
        try {
            /** @var array<string,array<int|string,string|bool|int>|string[]>|null $settings */
            $settings = $this->requestPost->get('fieldSettings');

            if (null === $settings) {
                throw new \RuntimeException('No field settings provided');
            }

            /* The updated availabilities and visibilities are in this array struct
             * where availability is *presumed* to be campus ids only (for now)
             * and visibility is *presumed* to be roletype strings only (for now)
             * imho it'd be better served seeing a campus node in here and also a
             * roletype node, so you know conclusively what these represent.
             * An additional node is also present, which contains other settings that
             * don't fit into the other categories
             * Each node doesn't always have all the possible keys, usually only those that are enabled
             *
                [fieldSettings] => Array
                    (
                        [availability] => Array
                            (
                                [1] => ?boolean
                                [2] => ?boolean
                            )

                        [visibility] => Array
                            (
                                [parent] => ?boolean
                                [student] => ?boolean
                                [staff] => ?boolean
                                [self] => ?boolean
                            )

                        [additional] => Array
                            (
                                [clickToReveal] => ?boolean
                                [iFrameLink] => ?string
                                [iFrameHeight] => ?int
                            )

                    )
             */

            /* First purge existing relations
             */
            $this->availabilityCampusMapper->deleteByColumn('profile_field_id', $field->getId());

            /* Then, if there are any campus ids chosen, reinsert these new ones
             * Otherwise leave it empty (this is a legitimate use case)
             */
            $newCampusIds = $settings[Payload::AVAILABILITY_NODE] ?? [];
            $newCampusIds = array_keys(array_filter($newCampusIds, fn($v) => (bool)$v));

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
            $newRoleTypes = $settings[Payload::VISIBILITY_NODE] ?? [];
            $newRoleTypes = array_keys(array_filter($newRoleTypes, fn($v) => (bool)$v));

            if (\count($newRoleTypes)) {
                foreach ($newRoleTypes as $newRoleType) {
                    $relation = $this->visibilityMapper->createModel()
                        ->setField($field)
                        ->setRoleType($newRoleType);

                    $this->visibilityMapper->save($relation);
                }
            }

            /* Apply additional settings
             * TODO: make this dependent on the field definition and include the other settings
             */
            $additionalSettings = $settings[Payload::ADDITIONAL_SETTINGS_NODE] ?? [];

            $field->setClickToReveal((int)(bool)($additionalSettings['clickToReveal'] ?? false));
            $this->fieldMapper->save($field);

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
    public function postSequence(string $roleType): void
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
            ]);

            $fields->sortByCallback(function (Model $a, Model $b) use ($sequence) {
                /* Sequence is actually a (int, string) tuple that represents
                 * (index, field name), not its primary key in profile_field.
                 */
                $aIndex = array_search($a->getField(), $sequence, true);
                $bIndex = array_search($b->getField(), $sequence, true);

                /* If it isn't found, put it at the start
                 * For example, Portrait Image should never appear in the $sequence array
                 * but should always be at the start of the list
                 */
                $aIndex = $aIndex === false ? PHP_INT_MIN : $aIndex;
                $bIndex = $bIndex === false ? PHP_INT_MIN : $bIndex;
                return $aIndex <=> $bIndex;
            });

            // Reset sequences for all fields to avoid duplicates
            $this->saveSequence($fields->getAll());

            $this->response = $this->responseAsJson(['success' => true]);
        } catch (\Exception $e) {
            $this->response = $this->responseAsJson(
                ['success' => false, 'message' => $e->getMessage()]
            )->withStatus(500);
        }
    }
}
