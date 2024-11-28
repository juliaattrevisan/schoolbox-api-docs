<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Tab;

use Alaress\Web\Controller\Base;
use Schoolbox\Role;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(
        private Mapper $tabMapper,
        private Availability\Campus\Mapper $availabilityCampusMapper,
        private Visibility\Mapper $visibilityMapper,
        private \Schoolbox\CampusMapper $campusMapper
    ) {
    }


    /**
     * POST Update tab settings for a particular tab
     *
     * @api   POST /adminv2/profileEditor/tab/{roleType}/{tabId}/settings
     * @since 24.1
     *
     * @param string $roleType The roletype to update
     * @param Model $tab  The tab to update
     * @throws \InvalidArgumentException
     */
    public function postSettings(string $roleType, Model $tab): void
    {
        try {
            $settings = $this->requestPost->get('tabSettings');

            if (null === $settings) {
                throw new \RuntimeException('No tab settings provided');
            }

            /* The updated availabilities and visibilities are in this array struct
             * where availability is *presumed* to be campus ids only (for now)
             * and visibility is *presumed* to be roletype strings only (for now)
             * imho it'd be better served seeing a campus node in here and also a
             * roletype node, so you know conclusively what these represent.
             *
                [tabSettings] => Array
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
            $this->availabilityCampusMapper->deleteByColumn('profile_tab_id', $tab->getId());

            /* Then, if there are any campus ids chosen, reinsert these new ones
             * Otherwise leave it empty (this is a legitimate use case)
             */
            /** @var int[] $newCampusIds */
            $newCampusIds = $settings['availability'] ?? [];

            if (\count($newCampusIds)) {
                $campuses = $this->campusMapper->findByColumn('id', $newCampusIds);

                foreach ($campuses as $campus) {
                    $relation = $this->availabilityCampusMapper->createModel()
                        ->setTab($tab)
                        ->setCampus($campus);

                    $this->availabilityCampusMapper->save($relation);
                }
            }

            /* First purge existing relations
             */
            $this->visibilityMapper->deleteByColumn('profile_tab_id', $tab->getId());

            /* Then, if there are any roletypes chosen, reinsert these new ones
             * Otherwise leave it empty (this is a legitimate use case)
             */
            /** @var string[] $newRoleTypes */
            $newRoleTypes = $settings['visibility'] ?? [];

            if (in_array(Role::STUDENT, $newRoleTypes)) {
                throw new \InvalidArgumentException('Cannot set tab visibility for ' . Role::STUDENT . '.');
            }

            if (\count($newRoleTypes)) {
                foreach ($newRoleTypes as $newRoleType) {
                    $relation = $this->visibilityMapper->createModel()
                        ->setTab($tab)
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
     * POST Resequence tabs for a particular role type
     *
     * Note that sequences are 1-indexed.
     *
     * @api   POST /adminv2/profileEditor/tab/{roleType}/sequence
     * @since 24.1
     *
     * @param string $roleType The roletype for which the sequence is updated
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function postSequence(string $roleType): void
    {
        try {
            /** @var string[]|null $sequence - The new sequence of tab IDs, in order */
            $sequence = $this->requestPost->get('sequence');

            if (!\is_array($sequence)) {
                throw new \RuntimeException('Invalid sequence provided');
            }

            /* When we resequence tabs, we aren't adding or removing them
             * which means we can presume to obtain all the necessary tabs
             * in one database call
             */
            $tabs = $this->tabMapper->findByColumnList([
                'roleType' => $roleType,
            ])->indexBy('tab');

            /* Sequence is actually a (int, string) tuple that represents
             * (index, tab name), not its primary key in profile_tab.
             */
            foreach ($sequence as $index => $tab) {
                $tabModel = $tabs->getOffset($tab);

                /* For some edge-casey reason, the model doesn't exist anymore;
                 * someone else might have deleted it at the same moment we were
                 * trying to resequence it
                 */
                if (null === $tabModel) {
                    continue;
                }

                $tabModel->setSequence($index + 1);

                $this->tabMapper->save($tabModel);
            }

            $this->response = $this->responseAsJson(['success' => true]);
        } catch (\Exception $e) {
            $this->response = $this->responseAsJson(
                ['success' => false, 'message' => $e->getMessage()]
            )->withStatus(500);
        }
    }
}
