<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Criteria;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Group;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\GroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\UsageMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Controller for the rubric criteria management form.
 */
class Controller extends BaseController
{
    /**
     * @var CriterionMapper
     */
    private $criterionMapper;


    /**
     * @var GroupMapper
     */
    private $groupMapper;

    /**
     * @var UsageMapper
     */
    private $usageMapper;


    /**
     * @var RubricMapper
     */
    private $rubricMapper;


    /**
     * @var ErrorMapper
     */
    private $errorMapper;


    /**
     * @var RosettaStone
     */
    private $rosettaStone;


    /**
     * Constructor
     *
     * @param CriterionMapper $criterionMapper
     * @param GroupMapper     $groupMapper
     * @param UsageMapper     $usageMapper
     * @param RubricMapper    $rubricMapper
     * @param ErrorMapper     $errorMapper
     * @param RosettaStone    $rosettaStone
     */
    public function __construct(
        CriterionMapper $criterionMapper,
        GroupMapper $groupMapper,
        UsageMapper $usageMapper,
        RubricMapper $rubricMapper,
        ErrorMapper $errorMapper,
        RosettaStone $rosettaStone
    ) {
        $this->criterionMapper = $criterionMapper;
        $this->groupMapper     = $groupMapper;
        $this->usageMapper     = $usageMapper;
        $this->rubricMapper    = $rubricMapper;
        $this->errorMapper     = $errorMapper;
        $this->rosettaStone    = $rosettaStone;
    }


    /**
     * View the rubric criteria form for a folder.
     *
     * @api   GET /rubric/criteria/{folderId}
     * @level 181
     * @param FolderModel $folder
     */
    public function getForm(FolderModel $folder): void
    {
        $this->getResponse()->content->set(
            $this
                ->buildHtmlOutput(
                    '@rubricCriteria/form.twig',
                    $this->buildTwigDataForForm($folder, $this->criterionMapper->findForFolder($folder->getId()))
                )
                ->setTitle(
                    sprintf(
                        'Manage Rubric %s for %s',
                        $this->rosettaStone->translate('rubricGroup', true, true),
                        $folder->getName()
                    )
                )
        );
    }


    /**
     * Save the rubric criteria for a folder.
     *
     * @api   POST /rubric/criteria/{folderId}
     * @level 181
     * @param FolderModel $folder
     */
    public function postForm(FolderModel $folder): void
    {
        $data = $this->preprocessPostData($this->request->post->get('crits', []));
        $this->deleteUnusedCriteria($folder, $data);
        $this->persistCriteria($folder, $data);

        // Done: redirect back to homepage, or AJAX
        if ($this->request->isXhr()) {
            $this->response->content->set(
                $this->buildJsonOutput([
                    'status'  => 200,
                    'message' => 'Criteria updated',
                ])
            );

        } else {
            $this->response->redirect->to('/homepage/' . $folder->getId());
        }
    }


    /**
     * Build Twig data for the form.
     *
     * @param FolderModel $folder
     * @param Criterion[] $criterionCollection
     * @return array
     */
    private function buildTwigDataForForm(FolderModel $folder, $criterionCollection): array
    {
        $topId = 0;

        // Group criterion list by each criterion's rubric group
        $groups = [];
        foreach ($criterionCollection as $criterion) {
            $groupId = $criterion->getGroup()->getId();
            $topId   = max($topId, $criterion->getId());

            // Build group item if it's not there
            if (!isset($groups[$groupId])) {
                $groups[$groupId] = [
                    'group'      => $criterion->getGroup(),
                    'usageTotal' => 0,
                    'criteria'   => [],
                ];
            }

            $usage                          = $this->usageMapper->findUsage($criterion->getId());
            $groups[$groupId]['usageTotal'] += \count($usage);
            $groups[$groupId]['criteria'][] = [
                'criterion' => $criterion,
                'usage'     => $usage,
            ];
        }

        return [
            'folder'              => $folder,
            'groups'              => $groups,
            'maxGroupId'          => \count($groups),
            'maxCritId'           => $topId + 1,
            'showCurriculumCodes' => $this->getConfigLoader()->get('curriculum_tagging_enabled'),
        ];
    }


    /**
     * Pre-process data from POST into a format which the save method can use.
     *
     * @param array $criteria
     * @return array
     */
    private function preprocessPostData(array $criteria): array
    {
        $data = [];
        foreach ($criteria as $group) {
            if (\count($group['items'])) {
                foreach ($group['items'] as $crit) {
                    $data[] = [
                        'groupId'   => (int)$group['id'],
                        'groupName' => $group['name'],
                        'id'        => (int)$crit['id'],
                        'name'      => $crit['name'],
                    ];
                }
            }
        }
        return $data;
    }


    /**
     * Delete any criteria that were not submitted in POST data.
     *
     * @param FolderModel $folder The folder to delete criteria from
     * @param array       $data   POST data, after having been pre-processed
     *                            (see `$this->preprocessPostData`)
     * @throws \Schoolbox\Mapper\Exception if a delete fails?
     */
    private function deleteUnusedCriteria(FolderModel $folder, array $data): void
    {
        // Which submission boxes have criteria deleted? We will need to ensure we don't leave in an invalid state.
        $affectedSubmissionBoxes = [];

        // work out the diff and delete unnecessary criteria
        $found = array_column($data, 'id');

        $criterionCollection = $this->criterionMapper->findForFolder($folder->getId());

        // Delete criteria not submitted
        foreach ($criterionCollection as $crit) {
            if (!\in_array($crit->getId(), $found, true)) {
                $usages = $this->usageMapper->findByColumn('criterion_id', $crit->getId());
                $this->criterionMapper->delete($crit);
                foreach ($usages as $usage) {
                    $box                                    = $usage->getSubmissionBox();
                    $affectedSubmissionBoxes[$box->getId()] = $box;
                }
            }
        }

        // Ensure that deletion of a criterion, and its descriptors, does not result in empty levels.
        $this->rubricMapper->deleteEmptyLevels(array_keys($affectedSubmissionBoxes));
    }


    /**
     * Persists criteria submitted in POST data.
     *
     * @param FolderModel $folder The folder to persist criteria for
     * @param array       $data   POST data, after having been pre-processed
     *                            (see `$this->preprocessPostData`)
     * @throws \Schoolbox\Mapper\Exception
     */
    private function persistCriteria(FolderModel $folder, array $data): void
    {
        /** @var Group[] $groupList */
        $groupList  = $this->groupMapper->createCollection();
        $parentType = Group::PARENT_TYPE_CATEGORY;
        $folderId   = $folder->getId();

        // iterate through list of provided criteria changes
        foreach ($data as $critData) {
            // Find the group; if it doesn't exist create it
            $groupId   = $critData['groupId'];
            $groupName = $critData['groupName'];
            if (!isset($groupList[$groupName])) {
                if ($groupId) {
                    try {
                        $group = $this->groupMapper->findOneByColumnList([
                            'id'          => $groupId,
                            'parent_id'   => $folderId,
                            'parent_type' => $parentType,
                        ]);
                    } catch (\Exception $e) {
                        // ID exists, but not in this context; abort
                        throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                    }
                } else {
                    $group = $this->groupMapper->findOrCreateOneForFolderByName($folderId, $groupName, false);
                }
                $this->groupMapper->save($group);
                $groupList[$groupName] = $group;
            }
        }

        foreach ($data as $critData) {
            // Now, find the criterion: if it doesn't exist create it
            $critId    = $critData['id'];
            $critName  = $critData['name'];
            $groupName = $critData['groupName'];
            $group     = $groupList[$groupName];
            if ($critId) {
                try {
                    $criterion = $this->criterionMapper->findOneByColumnList([
                        'id'              => $critId,
                        'rubric_group_id' => $group->getId(),
                    ]);
                    $criterion->setName($critName);

                    // Do not edit criteria that are in use on a class page, can edit in course though
                    $usage = $this->usageMapper->findByColumnList(['criterion_id' => $critId]);
                    if (\count($usage) && !($folder->hasCourseSpecification() || $folder->hasUnitSpecification())) {
                        continue; // possibly throw an error of some kind
                    }
                    $this->criterionMapper->save($criterion);

                } catch (\Exception $e) {
                    // ID exists, but not in this context; abort
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                }
            } else {
                // Create a new one
                $this->criterionMapper->findOrCreateOneForFolderByKey($folderId, $critName, $groupName, false);
            }
        }
    }
}
