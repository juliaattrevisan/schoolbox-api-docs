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
        $criterionCollection = $this->criterionMapper->findForFolder($folder->getId());

        $topId = 0;

        $criteriaList = [];
        if (\count($criterionCollection)) {

            /* Create group structure */
            $criteriaList = [];
            foreach ($criterionCollection as $crit) {
                $groupId                              = $crit->getGroup()->getId();
                $criteriaList[$groupId]['group']      = $crit->getGroup();
                $criteriaList[$groupId]['criteria'][] = $crit;
            }
        }

        $criterionData = [];
        foreach ($criteriaList as $critGroup) {
            /** @var \Schoolbox\Assessment\Component\SubmissionBox\Rubric\Group $group */
            $group = $critGroup['group'];
            /** @var \Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion[] $crits */
            $crits     = $critGroup['criteria'];
            $groupData = [
                'group'    => $group,
                'criteria' => [],
            ];

            $usageTotal = 0;

            foreach ($crits as $crit) {
                $usage      = $this->usageMapper->findUsage($crit->getId());
                $usageTotal += \count($usage);

                $groupData['criteria'][] = [
                    'criterion' => $crit,
                    'usage'     => $usage,
                ];

                /* @var $crit Criterion */
                $topId = max($topId, $crit->getId());
            }

            $groupData['usageTotal'] = $usageTotal;
            $criterionData[]         = $groupData;
        }

        $data = [
            'folder'     => $folder,
            'groups'     => $criterionData,
            'maxGroupId' => \count($criteriaList),
            'maxCritId'  => $topId + 1,
        ];

        $this->getResponse()->content->set(
            $this
                ->buildHtmlOutput('@rubricCriteria/form.twig', $data)
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
        $folderId = $folder->getId();
        $criteria = $this->request->post->get('crits');

        $data = [];
        foreach ($criteria as $group) {
            if (\count($group['items'])) {
                foreach ($group['items'] as $critId => $crit) {
                    $critId        = (int)substr($critId, 1);
                    $data[$critId] = [
                        'id'    => $group['id'],
                        'group' => $group['name'],
                        'name'  => $crit,
                    ];
                }
            }
        }

        // Which submission boxes have criteria deleted? We will need to ensure we don't leave in an invalid state.
        $affectedSubmissionBoxes = [];

        // work out the diff and delete unnecessary criteria
        $found = array_keys($data);

        $criterionCollection = $this->criterionMapper->findForFolder($folderId);

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

        // iterate through list of provided criteria changes
        /** @var Group[] $groupList */
        $groupList  = $this->groupMapper->createCollection();
        $parentType = Group::PARENT_TYPE_CATEGORY;
        foreach ($data as $critId => $critData) {

            // Find the group; if it doesn't exist create it
            $groupName = $critData['group'];
            if (!isset($groupList[$groupName])) {
                if (isset($critData['id'])) {
                    try {
                        $group = $this->groupMapper->findOneByColumnList([
                            'id'          => $critData['id'],
                            'parent_id'   => $folderId,
                            'parent_type' => $parentType,
                        ]);
                    } catch (\Exception $e) {
                        // ID exists, but not in this context; abort
                        $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                    }
                } else {
                    $group = $this->groupMapper->findOrCreateOneForFolderByName($folderId, $groupName);
                }
                $this->groupMapper->save($group);
                $groupList[$groupName] = $group;
            }

            // Now, find the criterion: if it doesn't exist create it
            $group = $groupList[$groupName];

            try {
                $criteria = $this->criterionMapper->findOneByColumnList([
                    'id'              => $critId,
                    'rubric_group_id' => $group->getId(),
                ]);
                $criteria->setName($critData['name']);

                //Do not edit criteria that are in use on a class page, can edit in course though
                $usage = $this->usageMapper->findByColumnList(['criterion_id' => $critId]);
                if (\count($usage) && !($folder->hasCourseSpecification() || $folder->hasUnitSpecification())) {
                    continue; // possibly throw an error of some kind
                }
            } catch (\Exception $e) {
                $criteria = new Criterion();
                $criteria->setGroup($group);
                $criteria->setName($critData['name']);
            }

            $this->criterionMapper->save($criteria);
        }

        // Done: redirect back to homepage, or AJAX
        if ($this->request->isXhr()) {
            $this->response->content->set(
                $this->buildJsonOutput([
                    'status'  => 200,
                    'message' => 'Criteria updated',
                ])
            );

        } else {
            $this->response->redirect->to('/homepage/' . $folderId);
        }
    }
}
