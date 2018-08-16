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
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionUsageMapper;
use Schoolbox\Assessment\Rubric\RowSet\RowSet;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
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
     * @var NodeMapper
     */
    private $nodeMapper;


    /**
     * @var CriterionUsageMapper
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
     * @param CriterionMapper      $criterionMapper
     * @param GroupMapper          $groupMapper
     * @param NodeMapper           $nodeMapper
     * @param CriterionUsageMapper $usageMapper
     * @param RubricMapper         $rubricMapper
     * @param ErrorMapper          $errorMapper
     * @param RosettaStone         $rosettaStone
     */
    public function __construct(
        CriterionMapper $criterionMapper,
        GroupMapper $groupMapper,
        NodeMapper $nodeMapper,
        CriterionUsageMapper $usageMapper,
        RubricMapper $rubricMapper,
        ErrorMapper $errorMapper,
        RosettaStone $rosettaStone
    ) {
        $this->criterionMapper = $criterionMapper;
        $this->groupMapper     = $groupMapper;
        $this->nodeMapper      = $nodeMapper;
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
                    $this->buildTwigDataForForm($this->rubricMapper->createRowSet($folder, true))
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
     * @param RowSet $rowSet
     * @return array
     */
    private function buildTwigDataForForm(RowSet $rowSet): array
    {
        $folder = $rowSet->getContext()->getBaseContext()->getFolder();
        return [
            'folder'                   => $folder,
            'rowSet'                   => $rowSet,
            'maxGroupId'               => max($rowSet->retrieveGroupIds()) + 1,
            'maxCritId'                => max($rowSet->retrieveCriterionIds()) + 1,
            'curriculumTaggingEnabled' => $this->isCurriculumTaggingEnabled(),
            'isSpecFolder'             => $folder->hasCourseSpecification() || $folder->hasUnitSpecification(),
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
                        'groupId'    => (int)$group['id'],
                        'groupName'  => $group['name'],
                        'groupCodes' => $group['curriculumCode'],
                        'id'         => (int)$crit['id'],
                        'name'       => $crit['name'],
                        'codes'      => $crit['curriculumCode'],
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

        // Criteria that are in use may not be deleted from non-spec folders
        $isSpecFolder = $folder->hasCourseSpecification() || $folder->hasUnitSpecification();

        // Delete criteria not submitted
        foreach ($criterionCollection as $crit) {
            if (!\in_array($crit->getId(), $found, true)) {
                // Prevent deletion of criteria in use on a non-spec folder
                $usages = $this->usageMapper->findCriterionUsage($crit->getId());
                if (!$isSpecFolder && \count($usages)) {
                    // TODO figure out a proper error
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                }

                $this->criterionMapper->delete($crit);
                foreach ($usages as $usage) {
                    $affectedSubmissionBoxes[] = $usage['submission_box_id'];
                }
            }
        }

        // Ensure that deletion of a criterion, and its descriptors, does not result in empty levels.
        $this->rubricMapper->deleteEmptyLevels($affectedSubmissionBoxes);
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
        $groupList    = $this->groupMapper->createCollection();
        $parentType   = Group::PARENT_TYPE_CATEGORY;
        $folderId     = $folder->getId();
        $isSpecFolder = $folder->hasCourseSpecification() || $folder->hasUnitSpecification();

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

                        // A group's name is changeable if:
                        // * Its folder is a course or unit spec
                        // * Any criteria within it aren't in use on any due works
                        // (Its curriculum codes are always changeable)
                        if ($isSpecFolder) {
                            $isChangeable = true;
                        } else {
                            $usage        = $this->usageMapper->findGroupUsage($groupId);
                            $isChangeable = (\count($usage) === 0);
                        }
                        if ($isChangeable) {
                            $group->setName($groupName);
                        }

                    } catch (\Exception $e) {
                        // ID exists, but not in this context; abort
                        throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                    }
                } else {
                    $group = $this->groupMapper->findOrCreateOneForFolderByName($folderId, $groupName, false);
                }

                if ($this->isCurriculumTaggingEnabled()) {
                    $group->setCurriculumNodes($this->nodeMapper->findByColumn('id', $critData['groupCodes']));
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

                    // A criterion's name is changeable if any of the following are true:
                    // * Its folder is a course or unit spec
                    // * Criterion isn't in use on any due works
                    // (Its curriculum codes are always changeable)
                    if ($isSpecFolder) {
                        $isChangeable = true;
                    } else {
                        $usage        = $this->usageMapper->findCriterionUsage($critId);
                        $isChangeable = (\count($usage) === 0);
                    }
                    if ($isChangeable) {
                        $criterion->setName($critName);
                    }

                } catch (\Exception $e) {
                    // ID exists, but not in this context; abort
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                }
            } else {
                // Create a new one
                $criterion = $this->criterionMapper->findOrCreateOneForFolderByKey(
                    $folderId,
                    $critName,
                    $groupName,
                    false
                );
            }

            if ($this->isCurriculumTaggingEnabled()) {
                $criterion->setCurriculumNodes($this->nodeMapper->findByColumn('id', $critData['codes']));
            }
            $this->criterionMapper->save($criterion);
        }
    }


    /**
     * Is curriculum tagging enabled?
     *
     * If not: don't save curriculum nodes, or show them in the form.
     *
     * @return bool
     */
    private function isCurriculumTaggingEnabled(): bool
    {
        return (bool)$this->getConfigLoader()->get('curriculum_tagging_enabled');
    }
}
