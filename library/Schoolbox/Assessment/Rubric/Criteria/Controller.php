<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Criteria;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionUsageMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\GroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
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
                    $this->buildTwigDataForForm($this->rubricMapper->retrieveRowSet($folder, true))
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
        // Retrieve, update and persist the row set
        $rowSet = $this->rubricMapper->retrieveRowSet($folder, true);
        $this->updateRowSet($rowSet, $this->request->post->get('crits', []));
        $this->rubricMapper->persistRowSet($rowSet, $folder->isSpecFolder(), $this->isCurriculumTaggingEnabled());

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
            'isSpecFolder'             => $folder->isSpecFolder(),
        ];
    }


    /**
     * Update `$rowSet` with the criterion settings from POST.
     *
     * @param RowSet $rowSet   The row set, pre-update.
     * @param array  $postData POST vars for the criteria for the form
     * @return RowSet
     */
    private function updateRowSet(RowSet $rowSet, array $postData): RowSet
    {
        $isSpecFolder = $rowSet->getContext()->getBaseContext()->getFolder()->isSpecFolder();


        // Update the whole thing
        $rowSet = $this->updateRowSetGroups($rowSet, $postData);
        foreach ($rowSet->getGroups() as $groupId => $group) {
            $rowSet->getGroups()[$groupId] = $this->updateRowSetCritieria(
                $group,
                $postData[$groupId] ?? null,
                $isSpecFolder
            );
        }
        return $rowSet;
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


    /**
     * Add newly-added rowset groups, remove newly-removed ones, and update
     * the name and curriculum codes of any that were changed.
     *
     * @param RowSet $rowSet
     * @param array  $postData
     * @return RowSet
     * @throws SchoolboxException if trying to remove something not allowed
     */
    private function updateRowSetGroups(RowSet $rowSet, array $postData): RowSet
    {
        $existingGroupIds = $rowSet->retrieveGroupIds();
        $postedGroupIds   = array_keys($postData);
        $isSpecFolder     = $rowSet->getContext()->getBaseContext()->getFolder()->isSpecFolder();


        // Add newly-added rowset groups
        foreach (array_diff($postedGroupIds, $existingGroupIds) as $newGroupId) {
            $rowSet->getGroups()[$newGroupId] = new \Schoolbox\Assessment\Rubric\RowSet\Group();
        }


        // Remove newly-removed rowset groups
        foreach (array_diff($existingGroupIds, $postedGroupIds) as $removedGroupId) {
            // Prevent removal if this group is in use
            // TODO figure out what the proper error should be at this point
            if (!$isSpecFolder && $rowSet->getGroups()[$removedGroupId]->isInUse()) {
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
            }
            unset($rowSet->getGroups()[$removedGroupId]);
        }


        // Remove any rowset groups that weren't removed in the previous step,
        // but have no criteria left.
        foreach ($rowSet->getGroups() as $groupId => $group) {
            $postedGroup = $postData[$groupId] ?? null;
            if (null === $postedGroup || empty($postedGroup['items'])) {
                // Prevent removal if this group is in use
                if (!$isSpecFolder && $group->isInUse()) {
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                }
                unset($rowSet->getGroups()[$groupId]);
                continue;
            }


            // Can I change the name of the group?
            $group->setName($postedGroup['name']);
            if ($this->isCurriculumTaggingEnabled()) {
                $group->setCurriculumNodes(
                    $this->nodeMapper->findByColumn('id', $postedGroup['curriculumCode'])
                );
            }
        }

        return $rowSet;
    }


    /**
     * Add newly-added rowset criteria, remove newly-removed ones, and update
     * the name and curriculum codes of any that were changed.
     *
     * @param \Schoolbox\Assessment\Rubric\RowSet\Group $group
     * @param array                                     $postedGroup
     * @param bool                                      $isSpecFolder
     * @return \Schoolbox\Assessment\Rubric\RowSet\Group
     * @throws SchoolboxException if trying to remove something not allowed
     */
    private function updateRowSetCritieria(
        \Schoolbox\Assessment\Rubric\RowSet\Group $group,
        array $postedGroup,
        bool $isSpecFolder
    ): \Schoolbox\Assessment\Rubric\RowSet\Group {
        // Which criteria were passed?
        $existingCriterionIds = $group->retrieveCriterionIds();
        $postedCriterionIds   = array_keys($postedGroup['items']);
        $postedCriteria       = $postedGroup['items'];


        // Add newly-added criteria
        foreach (array_diff($postedCriterionIds, $existingCriterionIds) as $newCriterionId) {
            $group->getCriteria()[$newCriterionId] = new \Schoolbox\Assessment\Rubric\RowSet\Criterion();
        }


        // Remove existing criteria
        foreach (array_diff($existingCriterionIds, $postedCriterionIds) as $removedCriterionId) {
            if (!$isSpecFolder && $group->getCriteria()[$removedCriterionId]->isInUse()) {
                throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
            }
            unset($group->getCriteria()[$removedCriterionId]);
        }


        // Update name of any criteria if I can
        foreach ($group->getCriteria() as $criterionId => $criterion) {
            $postedCriterion = $postedCriteria[$criterionId] ?? null;
            if (null === $postedCriterion) {
                continue;
            }

            // Can I change the name of the criterion?
            $criterion->setName($postedCriterion['name']);
            if ($this->isCurriculumTaggingEnabled()) {
                $criterion->setCurriculumNodes(
                    $this->nodeMapper->findByColumn('id', $postedCriterion['curriculumCode'])
                );
            }
        }

        return $group;
    }
}
