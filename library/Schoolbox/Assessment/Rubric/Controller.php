<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;

use Alaress\Web\Controller\Base as BaseController;
use Alaress\Web\Template\DebugTwig;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Group;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\GroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\UsageMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Model as File;
use Url;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;

class Controller extends BaseController
{

    /** @var Factory */
    private $factory;

    /** @var RubricMapper */
    private $mapper;


    /**
     * @return Factory
     */
    public function getFactory()
    {
        return $this->factory;
    }


    /**
     * @param Factory $factory
     * @return self
     */
    public function setFactory(Factory $factory)
    {
        $this->factory = $factory;
        return $this;
    }


    /**
     * @return RubricMapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }


    /**
     * @param RubricMapper $mapper
     * @return self
     */
    public function setMapper(RubricMapper $mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * AJAX method for creating a new group or criterion
     *
     * @api POST /rubric
     * @level 156
     */
    public function create()
    {
        $post = $this->getRequest()->post;

        $formField = $post->get('formField', 'rb');

        $context = [
            DebugTwig::DEBUG_WRAP => false,
            'formField' => $formField,
            'rubric' => [
                'id' => $post->get('rubricId'),
                'levels' => $post->get('levelCount') ? array_fill(0, $post->get('levelCount'), ':)') : [],
            ],
        ];

        $folderId = $post->get('folderId');
        if(!$folderId){
            $this->sendJSONError('Must supply a folder Id');
            return;
        }

        if ($post->get('type') == 'criterion') {

            $group = $post->get('groupName');
            $name  = $post->get('name');
            if(!$group){
                $this->sendJSONError('Must supply a Group for new Criterion');
                return;
            }

            $criteria      = CriterionMapper::getInstance()->findForFolder($folderId);
            $proposedGroup = $this->mapper->normaliseForComparison($group);
            $proposedName  = $this->mapper->normaliseForComparison($name);
            foreach ($criteria as $criterion){
                $normGroup = $this->mapper->normaliseForComparison($criterion->getGroup()->getName());
                $normName = $this->mapper->normaliseForComparison($criterion->getName());
                if($normGroup == $proposedGroup && $normName == $proposedName){
                    $this->sendJSONError("Indicator already exists in this Group");
                    return;
                }
            }

            $context['rowspan'] = null;
            $context['group'] = [
                'id' => $post->get('groupId')
            ];
            $context['crit'] = $this->saveNewCriterion($folderId, $group, $name);

            $template = 'group-row-editable';
        }

        elseif ($post->get('type') == 'group') {

            // Ensure the proposed Group name is not already in use.
            $criteria = CriterionMapper::getInstance()->findForFolder($folderId);
            $proposed = $this->mapper->normaliseForComparison($post->get('name'));
            foreach ($criteria as $criterion){
                $grouping = $this->mapper->normaliseForComparison($criterion->getGroup()->getName());
                if($proposed == $grouping){
                    $this->sendJSONError("Group name already exists in this context");
                    return;
                }
            }

            $context['rowspan'] = 1;
            $context['group'] = [
                'id'   => rand(1, PHP_INT_MAX), // this is just so the inputs on the page are unique
                'name' => $post->get('name'),
                'used' => false,
            ];

            $template = 'group-editable';

        } else {
            return;
        }


        $this->getResponse()->content->set([
            'data'     => $context,
            'format'   => 'nochrome',
            'template' => "@rubric/$template.twig"
        ]);

    }

    /**
     * Sends a JSON error.
     *
     * @param mixed $data
     * @param int $statusCode
     */
    protected function sendJSONError($data, $statusCode = 400)
    {
        $this->getResponse()->status->set($statusCode);
        $this->getResponse()->content->set([
            'data'     => $data,
            'format'   => 'json',
        ]);
    }


    /**
     * Return a Twigged shard generated from the file ref given
     *
     * @api POST /rubric/import/{storageTuple}
     * @level 156
     *
     * @param File $file
     */
    public function import(File $file)
    {
        $post = $this->getRequest()->post;

        try {
            if ($file->getMime(true) != 'text/plain') {
                throw new \Schoolbox\Resource\Exception("Not a plaintext CSV file, cannot parse");
            }

            if ($boxId = $post->get('boxId')) {
                $box = SubmissionBoxMapper::getInstance()->findById($boxId);
                $folderId = $box->getCategory()->getId();

            } elseif ($folderId = $post->get('folderId')) {
                $box = SubmissionBoxMapper::getInstance()->createModel()->setId(0);

            } else {
                // can't work with that
                throw new \Schoolbox\Mapper\Exception('No folder or box ID set');
            }

            // attempt to build the grid
            $maxLevels = $this->getDi()->get('config')->get('rubric_max_levels');
            $grid = $this->getMapper()->buildFromCSV($file, $folderId, $maxLevels)->setSubmissionBox($box);

        } catch (\Exception $e) {

            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set([
                'format' => 'json',
                'data'   => $e->getMessage()
            ]);
            return;
        }

        $context = [
            'formField' => $post->get('formField', 'rb'),
            'rubric'    => $grid->toContext()
        ];

        $this->getResponse()->content->set([
            'data'     => $context,
            'format'   => 'nochrome',
            'template' => '@rubric/grid-editable.twig'
        ]);
    }

    /**
     * Download the given rubric as a CSV
     *
     * @api POST /rubric/csv
     * @level 156
     */
    public function download()
    {
        $post     = $this->getRequest()->post->get('rb');
        $boxId    = key($post);
        $grid     = (new Factory())->buildFromPostData($post, false);
        $basename = (new Url(SubmissionBoxMapper::getInstance()->findById($boxId)->getName()))->getSlug();
        $content  = $this->array2csv($grid->toGrid());

        $headers  = $this->getResponse()->headers;

        $headers->set('Pragma', ' ');
        $headers->set('Cache-Control', ' ');
        $headers->set('Content-Type', 'text/csv');
        $headers->set('Content-Length', strlen($content));
        $headers->set('Content-Disposition', "attachment; filename=\"$basename-rubric.csv\"");

        $this->getResponse()->content->set([
            'format' => 'file',
            'data'   => $content
        ]);
    }


    /**
     * View the rubric criteria form for a folder.
     *
     * @api GET /rubric/criteria/{folderId}
     * @level 181
     * @param FolderModel $folder
     */
    public function getCriteriaForm(FolderModel $folder): void
    {
        $criterionCollection = CriterionMapper::getInstance()->findForFolder($folder->getId());

        $topId = 0;

        $criteriaList  = [];
        if (\count($criterionCollection)) {

            /* Create group structure */
            $criteriaList = [];
            foreach ($criterionCollection as $crit) {
                $groupId = $crit->getGroup()->getId();
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
                $usage      = UsageMapper::getInstance()->findUsage($crit->getId());
                $usageTotal += \count($usage);

                $groupData['criteria'][] = [
                    'criterion'  => $crit,
                    'usage'      => $usage,
                ];

                /* @var $crit Criterion */
                $topId = max($topId, $crit->getId());
            }

            $groupData['usageTotal'] = $usageTotal;
            $criterionData[] = $groupData;
        }

        $data = [
            'folder'     => $folder,
            'groups'     => $criterionData,
            'maxGroupId' => count($criteriaList),
            'maxCritId'  => $topId + 1,
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@rubric/criteriaForm.twig', $data)
//            ->setTitle(
//                sprintf('Manage Rubric %s for %s'),
//            )
        );
    }


    /**
     * Save the rubric criteria for a folder.
     *
     * @api POST /rubric/criteria/{folderId}
     * @level 181
     * @param FolderModel $folder
     */
    public function postCriteriaForm(FolderModel $folder): void
    {
        $folderId = $folder->getId();
        $criteria = $this->request->post->get('crits');

        foreach ($criteria as $group) {
            if (\count($group['items'])) {
                foreach ($group['items'] as $critId => $crit) {
                    $critId = (int)substr($critId, 1);
                    $data[$critId] = [
                        'id'    => $group['id'],
                        'group' => $group['name'],
                        'name'  => $crit,
                    ];
                }
            }
        }

        $criterionMapper = CriterionMapper::getInstance();
        $groupMapper     = GroupMapper::getInstance();

        // Which submission boxes have criteria deleted? We will need to ensure we don't leave in an invalid state.
        $affectedSubmissionBoxes = SubmissionBoxMapper::getInstance()->createCollection();

        // work out the diff and delete unnecessary criteria
        $found = array_keys($data);

        $criterionCollection = $criterionMapper->findForFolder($folderId);

        // Delete criteria not submitted
        foreach ($criterionCollection as $crit) {
            if (!\in_array($crit->getId(), $found, true)) {
                try {
                    $usages = UsageMapper::getInstance()->findByColumn('criterion_id', $crit->getId());
                    $criterionMapper->delete($crit);
                    foreach ($usages as $usage){
                        $box = $usage->getSubmissionBox();
                        $affectedSubmissionBoxes[$box->getId()] = $box;
                    }

                } catch (\Exception $e) {
                    // Couldn't delete criteria!
                    ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                }
            }
        }

        // Ensure that deletion of a criterion, and its descriptors, does not result in empty levels.
        RubricMapper::getInstance()->deleteEmptyLevels($affectedSubmissionBoxes->keys());

        // iterate through list of provided criteria changes
        /** @var Group[] $groupList */
        $groupList  = $groupMapper->createCollection();
        $parentType = Group::PARENT_TYPE_CATEGORY;
        foreach ($data as $critId => $critData) {

            // Find the group; if it doesn't exist create it
            $groupName = $critData['group'];
            if (!isset($groupList[$groupName])) {
                if (isset($critData['id'])) {
                    try {
                        $group = $groupMapper->findOneByColumnList([
                            'id'          => $critData['id'],
                            'parent_id'   => $folderId,
                            'parent_type' => $parentType,
                        ]);
                    } catch (\Exception $e) {
                        // ID exists, but not in this context; abort
                        // TODO inject error mapper
                        ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                    }
                } else {
                    $group = $groupMapper->findOrCreateOneForFolderByName($folderId, $groupName);
                }
                $groupMapper->save($group);
                $groupList[$groupName] = $group;
            }

            // Now, find the criterion: if it doesn't exist create it
            $group = $groupList[$groupName];

            try {
                $criteria = $criterionMapper->findOneByColumnList([
                    'id'              => $critId,
                    'rubric_group_id' => $group->getId(),
                ]);
                $criteria->setName($critData['name']);

                //Do not edit criteria that are in use on a class page, can edit in course though
                $usage = UsageMapper::getInstance()->findByColumnList(['criterion_id' => $critId]);
                if (\count($usage) && !($folder->hasCourseSpecification() || $folder->hasUnitSpecification())) {
                    continue; // possibly throw an error of some kind
                }
            } catch (\Exception $e) {
                $criteria = new Criterion();
                $criteria->setGroup($group);
                $criteria->setName($critData['name']);
            }

            $criterionMapper->save($criteria);
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


    /**
     * The array data should obviously be in a CSV-compatible format (ie. rectangle)
     *
     * @param array $array
     * @return string
     */
    protected function array2csv(array $array)
    {
        $rows = [];

        foreach ($array as $row) {
            $rows[] = '"' . implode('","', $row) . '"';
        }

        return implode("\n", $rows);
    }


    /**
     * @param int    $folderId
     * @param string $grouping
     * @param string $name
     * @return array
     */
    protected function saveNewCriterion($folderId, $grouping, $name)
    {
        $model = CriterionMapper::getInstance()->findOrCreateOneForFolderByKey($folderId, $name, $grouping);

        return [
            'name'        => $model->getName(),
            'id'          => $model->getId(),
            'descriptors' => [],
            'used'        => false,
        ];
    }


    public function testGeneric()
    {
        $rid = rand(10000, 100000);

        $postdata = [
            $rid => [
                'level' => [
                    [ 'name' => 'Wow!' ],
                    [ 'name' => 'Expected' ],
                    [ 'name' => 'Building' ],
                    [ 'name' => 'Emerging' ],
                    [ 'name' => 'Not Shown' ],
                    [ 'name' => 'foo' ],
                    [ 'name' => 'bar' ],
                    [ 'name' => 'baz' ],
                    [ 'name' => 'bing' ],
                    [ 'name' => 'boop' ],
                    [ 'name' => 'bang' ],
                ],
                'group' => [
                    21 => [ 'name' => 'Language' ],
                    22 => [ 'name' => 'Content' ],
                    23 => [ 'name' => 'Conversation Skills' ],
                ],
                'criterion' => [
                    31 => [
                        'groupId' => 21,
                        'on'      => 1,
                        'name'    => 'Grammar'
                    ],
                    32 => [
                        'groupId' => 21,
                        'on'      => 1,
                        'name'    => 'Language Used'
                    ],
                    33 => [
                        'groupId' => 22,
                        'on'      => 1,
                        'name'    => 'Card Information'
                    ],
                    34 => [
                        'groupId' => 23,
                        'on'      => 1,
                        'name'    => 'Understanding'
                    ],
                    35 => [
                        'groupId' => 23,
                        'on'      => 1,
                        'name'    => 'Responses to Questions'
                    ],
                    36 => [
                        'groupId' => 23,
                        'on'      => 1,
                        'name'    => 'Pronunciation'
                    ],
                ],
                'descriptor' => [
                    31 => [
                        [
                            'text' => 'Grammar is correct',
                            'points' => 4
                        ],
                        [
                            'text' => 'Grammatical errors are made (eg. agreements, verb conjugation, gender)',
                            'points' => 3
                        ],
                        [
                            'text' => 'Grammatical errors hinder understanding in some responses',
                            'points' => 2
                        ],
                        [
                            'text' => '',
                            'points' => ''
                        ],
                        [
                            'text' => 'Grammatical errors hinder understanding',
                            'points' => 0
                        ],
                    ],
                    32 => [
                        [
                            'text' => '',
                            'points' => ''
                        ],
                        [
                            'text' => 'Student speaks in French',
                            'points' => 3
                        ],
                        [
                            'text' => '',
                            'points' => ''
                        ],
                        [
                            'text' => 'Student attempts to speak in French but reverts to English for some words',
                            'points' => 1
                        ],
                        [
                            'text' => 'Student speaks in English',
                            'points' => 0
                        ],
                    ],
                    33 => [
                        [
                            'text' => 'Student completes all required components of the card and adds own phrases',
                            'points' => 4
                        ],
                        [
                            'text' => 'Student completes all required components of the card',
                            'points' => 3
                        ],
                        [
                            'text' => 'Student attempts to cover all required information on the card',
                            'points' => 2
                        ],
                        [
                            'text' => '',
                            'points' => ''
                        ],
                        [
                            'text' => 'No content covered',
                            'points' => 0
                        ],
                    ],
                    34 => [
                        [
                            'text' => 'Student understands all questions asked and information given',
                            'points' => 4
                        ],
                        [
                            'text' => 'Student understands all questions and information with help from the teacher',
                            'points' => 3
                        ],
                        [
                            'text' => 'Student understands most questions and information with help from the teacher',
                            'points' => 2
                        ],
                        [
                            'text' => 'Student relies heavily on the teacher for comprehension',
                            'points' => 1
                        ],
                        [
                            'text' => 'No understanding of questions asked or information given',
                            'points' => 0
                        ],
                    ],
                    35 => [
                        [
                            'text' => 'Student responds to question in full sentences',
                            'points' => 4
                        ],
                        [
                            'text' => 'Student responds to all questions',
                            'points' => 3
                        ],
                        [
                            'text' => 'Student responds to some questions with help from the teacher',
                            'points' => 2
                        ],
                        [
                            'text' => 'Student relies heavily on the teacher to form responses to questions',
                            'points' => 1
                        ],
                        [
                            'text' => '',
                            'points' => ''
                        ],
                    ]
                ],
                [   31 => [ [ 'text' => 'Student relies heavily on the teacher to form responses to questions' ] ]   ],
                [   31 => [ [ 'text' => 'Student relies heavily on the teacher to form responses to questions' ] ]   ],
                [   31 => [ [ 'text' => 'Student relies heavily on the teacher to form responses to questions' ] ]   ],
                [   31 => [ [ 'text' => 'Student relies heavily on the teacher to form responses to questions' ] ]   ],
                [   31 => [ [ 'text' => 'Student relies heavily on the teacher to form responses to questions' ] ]   ],
                [   31 => [ [ 'text' => 'Student relies heavily on the teacher to form responses to questions' ] ]   ],
            ]
        ];

        // level cutoff
        if (isset($_GET['levels']) && $_GET['levels']) {
            $postdata[$rid]['level'] = array_slice($postdata[$rid]['level'], 0, $_GET['levels']);
            $postdata[$rid]['descriptor'] = array_slice($postdata[$rid]['descriptor'], 0, $_GET['levels']);

        }

        $this->outputTest($this->getFactory()->buildFromPostData($postdata, true));
    }
    public function testBox($boxId)
    {
        $this->outputTest($this->getMapper()->findBySubmissionBox(SubmissionBoxMapper::getInstance()->findById($boxId)));
    }
    public function testCSV($boxId)
    {
        $formField = 'rubberick';

        $grid = $this->getMapper()->findBySubmissionBox(SubmissionBoxMapper::getInstance()->findById($boxId));

        $zone = new Zone($formField);
        $zone->setAttachPrompt('Import a rubric CSV')
            ->setMimeFilter('text/csv');

        $this->getResponse()->content->set([
            'data' => [
                'formField'  => $formField,
                'rubric'     => $grid->toContext(),
                'attachzone' => $zone->getTwigContext(),
                'test'       => $boxId
            ],
            'template' => "@rubric/grid-csv.twig"
        ]);

    }
    public function testFolder($folderId)
    {
        $folderLookup = [$folderId];

        // Determine if the specified folder has a UnitSpec
        /** @var UnitSpecification $unitSpec */
        $unitSpec = UnitSpecificationMapper::getInstance()->findByColumn('folder_id', $folderId)->first();
        if($unitSpec){
            // Add the Unit's Course's Folder's ID
            $folderLookup[] = $unitSpec->getCourse()->getFolder()->getId();
        }

        $this->outputTest($this->getMapper()->createForFolder($folderLookup[0], $folderLookup[1]));
    }

    protected function outputTest(Rubric $grid)
    {
        $this->getResponse()->content->set([
            'data' => [
                'formField' => 'rubberick',
                'rubric' => $grid->toContext()
            ],
            'template' => '@rubric/grid-editable.twig'
        ]);
    }

}
