<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;

use Alaress\Json\Decorator\LinkDecorator;
use Alaress\Json\Decorator\PropertyDecorator;
use Alaress\Web\Controller\Base as BaseController;
use Alaress\Web\Template\DebugTwig;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\GroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Rubric\Grid\Exporter\ArrayExporter;
use Schoolbox\Assessment\Rubric\Grid\Factory as GridFactory;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\Grid\Level;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Storage\Model as File;
use Url;

class Controller extends BaseController
{
    /**
     * @var RubricMapper
     */
    private $mapper;


    /**
     * @var AssessmentDataSource
     */
    private $assessmentDataSource;


    /**
     * Constructor
     *
     * @param RubricMapper         $mapper
     * @param AssessmentDataSource $assessmentDataSource
     */
    public function __construct(
        RubricMapper $mapper,
        AssessmentDataSource $assessmentDataSource
    ) {
        $this->mapper               = $mapper;
        $this->assessmentDataSource = $assessmentDataSource;
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
            'markPerCapability' => $post->getBool('markPerCapability', false),
            'useMarkRange'      => $post->getBool('useMarkRange', false),
        ];

        $folderId = $post->get('folderId');
        if(!$folderId){
            $this->sendJSONError('Must supply a folder Id');
            return;
        }


        // Build a rubric grid
        $assessmentId = $post->getInt('rubricId', 0);

        // Rubric grid needs to reflect state in form, not persisted state
        $rubricRowSet = $this->mapper->retrieveRowSet((new FolderModel())->setId($folderId), false);
        $rubricGrid   = new Grid((new DueWork())->setId($assessmentId), $rubricRowSet);

        // Fill in each level
        $levelCount = $post->getInt('levelCount', 0);
        for ($i = 0; $i < $levelCount; $i++) {
            $rubricGrid->getLevels()->add(new Level());
        }
        $context['rubricGrid'] = $rubricGrid;


        if ($post->get('type') == 'criterion') {

            $groupIndex = $post->get('groupIndex');
            $groupName  = $post->get('groupName');
            $critName   = $post->get('name');
            if(!$groupName){
                $this->sendJSONError('Must supply a Group for new Criterion');
                return;
            }

            $criterionMapper = CriterionMapper::getInstance();
            $criteria        = $criterionMapper->findForFolder($folderId);
            $proposedGroup   = $this->mapper->normaliseForComparison($groupName);
            $proposedName    = $this->mapper->normaliseForComparison($critName);
            foreach ($criteria as $criterion){
                $normGroup = $this->mapper->normaliseForComparison($criterion->getGroup()->getName());
                $normName  = $this->mapper->normaliseForComparison($criterion->getName());
                if ($normGroup === $proposedGroup && $normName === $proposedName){
                    // TODO localise!!!!!
                    $this->sendJSONError("Indicator already exists in this Group");
                    return;
                }
            }

            $criterionModel = $criterionMapper->findOrCreateOneForFolderByKey($folderId, $critName, $groupName);

            $group = (new \Schoolbox\Assessment\Rubric\RowSet\Capability())
                ->setId($criterionModel->getGroup()->getId())
                ->setName($groupName);

            // Don't have to save anything yet
            $criterion = (new \Schoolbox\Assessment\Rubric\RowSet\Indicator())
                ->setId($criterionModel->getId())
                ->setName($critName);

            $context['rowspan']    = null;
            $context['group']      = $group;
            $context['crit']       = $criterion;
            $context['groupIndex'] = $groupIndex;

            $template = 'group-row-editable';
        }

        elseif ($post->get('type') == 'group') {

            // Does the list of crits/groups really need to be saved now?
            // For some reason, groups aren't saved whilst criteria are?
            // Probably because groups literally couldn't exist w/o criteria
            $groupName = $post->get('name');

            // Next step: figure out what gets passed in
            $group = (new \Schoolbox\Assessment\Rubric\RowSet\Capability())
//                ->setId(rand(1, PHP_INT_MAX))
                ->setName($groupName);

            // Ensure the proposed Group name is not already in use.
            $groupModels = GroupMapper::getInstance()->findForFolder($folderId);
            $proposed    = $this->mapper->normaliseForComparison($groupName);
            foreach ($groupModels as $groupModel) {
                $grouping = $this->mapper->normaliseForComparison($groupModel->getName());
                if ($proposed === $grouping) {
                    // TODO localise
                    $this->sendJSONError("Group name already exists in this context");
                    return;
                }
            }

            $context['rowspan']    = 1;
            $context['group']      = $group;
            $context['groupIndex'] = $post->get('groupIndex');

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
                $box    = SubmissionBoxMapper::getInstance()->findById($boxId);
                $folder = $box->getCategory();

            } elseif ($folderId = $post->get('folderId')) {
                $box    = SubmissionBoxMapper::getInstance()->createModel()->setId(0);
                $folder = $box->getCategory()->setId($folderId);

            } else {
                // can't work with that
                throw new \Schoolbox\Mapper\Exception('No folder or box ID set');
            }

            // attempt to build the grid
            $maxLevels = $this->getDi()->get('config')->get('rubric_max_levels');
            [$postData, $usingMarkPerCapability, $usingMarkRange] = $this->mapper->buildFromCSV(
                $file,
                $folder->getId(),
                $maxLevels
            );

            $rubricRowSet = $this->mapper->retrieveRowSet($folder, true);
            $dummyDueWork = (new DueWork())
                ->setId($box->getAssessmentModel()->getId())
                ->setMarkPerCapability($usingMarkPerCapability)
                ->setUseMarkRange($usingMarkRange);
            $rubricGrid   = new Grid($dummyDueWork, $rubricRowSet);
            $rubricGrid   = (new GridFactory())
                ->buildFromPostData($rubricGrid, [$box->getAssessmentModel()->getId() => $postData], false);

        } catch (\Exception $e) {

            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set([
                'format' => 'json',
                'data'   => $e->getMessage()
            ]);
            return;
        }

        $context = [
            'formField'              => $post->get('formField', 'rb'),
            'rubricGrid'             => $rubricGrid,
            'usingMarkPerCapability' => $usingMarkPerCapability,
            'usingMarkRange'         => $usingMarkRange,
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
        $post     = $this->request->post->get('rb');
        $boxId    = key($post);

        // Note: I can't download rubrics for due works that don't exist yet
        $box      = $this->assessmentDataSource->findById($boxId);
        if (!$box instanceof DueWork) {
            // This is an error. How do I generate one?
            $this->sendError('Can only download rubrics for due work');
            return;
        }
        $box->setMarkPerCapability($this->request->post->getBool('markPerCapability') ?? false)
            ->setUseMarkRange($this->request->post->getBool('useMarkRange') ?? false);

        $grid     = (new GridFactory())->buildFromPostData(
            $this->mapper->retrieveGrid($box, false),
            $post,
            false
        );
        $basename = (new Url($box->getTitle()))->getSlug();
        $content  = $this->array2csv((new ArrayExporter())->export($grid));

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
     * Determines whether a rubric submitted via POST will invalidate marks.
     *
     * @api POST /rubric/determineMarkInvalidation
     * @level 181
     */
    public function determineMarkInvalidation(): void
    {
        $post  = $this->request->post->get('rb');
        $assessmentId = key($post);
        $assessment   = $this->assessmentDataSource->findByIdForReview($assessmentId);
        if (!$assessment instanceof DueWork) {
            $this->sendError('Can only determine mark invalidation for due work');
            return;
        }
        $assessment->setMarkPerCapability($this->request->post->getBool('markPerCapability', false))
            ->setUseMarkRange($this->request->post->getBool('useMarkRange', false));


        // Need to fetch current grid and proposed grid's base separately,
        // because of object references
        $currentGrid  = $this->mapper->retrieveGrid($assessment, false);
        $proposedGrid = (new GridFactory())->buildFromPostData(
            $this->mapper->retrieveGrid($assessment, false),
            $post,
            false
        );


        // Determine compatibility
        $currentRubricUse = $assessment->getUseRubric();
        if ($currentRubricUse) {
            $proposedRubricUse = $this->request->post->getBool('useRubric', false);
            if ($proposedRubricUse) {
                // Does the current and proposed due work both use rubrics?
                // Need to ask whether compatible
                $isCompatible = $proposedGrid->isCompatibleWith($currentGrid);

            } else {
                // Did rubric marks get switched off in the form?
                // That's always incompatible
                $isCompatible = false;
            }

        } else {
            // Does the due work currently not use rubrics?
            // That's always compatible
            $isCompatible = true;
        }


        // Is the proposed grid itself not valid?
        // Doesn't matter for the purpose of this exercise
        $response = [
            'compatible'    => $isCompatible,
            'affectedUsers' => [],
        ];
        if (!$isCompatible) {
            $jsonDecorator = new PropertyDecorator();
            $linkDecorator = new LinkDecorator($this->router, $this->authorizer);

            // Determine who's actually affected?
            foreach ($assessment->getAllParticipants() as $participant) {
                $user   = $participant->getUser();
                $userId = $user->getId();

                // Instructor-given feedback
                if ($this->mapper->hasInstructorMarkedGrid($participant)) {
                    $response['affectedUsers'][$userId]['instructorGiven'] = true;
                }

                // Self-assessment feedback
                if ($assessment->isSelfAssessable() && $this->mapper->hasSelfAssessedMarkedGrid($participant)) {
                    $response['affectedUsers'][$userId]['selfAssess'] = true;
                }

                // If either type of feedback set: set the user as affected
                if (!empty($response['affectedUsers'][$userId])) {
                    $response['affectedUsers'][$userId]['user'] = $user->jsonSerialize($jsonDecorator, $linkDecorator);
                }
            }
        }


        // Response code is always 200:
        // this is a valid request even if marks would've been invalidated.
        $this->response->status->setCode(200);
        $this->response->content->set($this->buildJsonOutput($response));
    }


    /**
     * The array data should obviously be in a CSV-compatible format (ie. rectangle)
     *
     * @param array $array
     * @return string
     */
    protected function array2csv(array $array)
    {
        $fp = fopen('php://temp', 'r+b');
        foreach ($array as $row) {
            $row = array_map('trim', $row);
            fputcsv($fp, $row);
        }
        rewind($fp);
        $result = stream_get_contents($fp);
        fclose($fp);
        return $result;
    }
}
