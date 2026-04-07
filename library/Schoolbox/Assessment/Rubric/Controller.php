<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric;

use Alaress\Json\Decorator\LinkDecorator;
use Alaress\Json\Decorator\PropertyDecorator;
use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use UnexpectedValueException;
use Schoolbox\Assessment\Rubric\DataModel\Capability;
use Schoolbox\Assessment\Rubric\DataModel\Indicator;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\Grid\Exporter\ArrayExporter;
use Schoolbox\Assessment\Rubric\Grid\Factory as GridFactory;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\Grid\Level;
use Schoolbox\Assessment\Rubric\Grid\Translator\ModelToArray as RubricGridTranslator;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Storage\Model as File;
use Url;

class Controller extends BaseController
{
    /**
     * @var RubricDataSource
     */
    private $mapper;


    /**
     * @var AssessmentDataSource
     */
    private $assessmentDataSource;


    /**
     * @var FolderMapper
     */
    private $folderMapper;


    /**
     * @var Capability\Mapper
     */
    private $capabilityMapper;


    /**
     * @var Indicator\Mapper
     */
    private $indicatorMapper;


    /**
     * @var RosettaStone
     */
    private $rosettaStone;


    /**
     * @var MarkTypeMapper
     */
    private $markTypeMapper;


    /**
     * @var Group\Mapper
     */
    private $gradeGroupMapper;


    /**
     * Constructor
     *
     * @param RubricDataSource     $mapper
     * @param AssessmentDataSource $assessmentDataSource
     * @param FolderMapper         $folderMapper
     * @param Capability\Mapper    $capabilityMapper
     * @param Indicator\Mapper     $indicatorMapper
     * @param RosettaStone         $rosettaStone
     * @param MarkTypeMapper       $markTypeMapper
     * @param Group\Mapper         $gradeGroupMapper
     */
    public function __construct(
        RubricDataSource $mapper,
        AssessmentDataSource $assessmentDataSource,
        FolderMapper $folderMapper,
        Capability\Mapper $capabilityMapper,
        Indicator\Mapper $indicatorMapper,
        RosettaStone $rosettaStone,
        MarkTypeMapper $markTypeMapper,
        Group\Mapper $gradeGroupMapper
    ) {
        $this->mapper               = $mapper;
        $this->assessmentDataSource = $assessmentDataSource;
        $this->folderMapper         = $folderMapper;
        $this->capabilityMapper     = $capabilityMapper;
        $this->indicatorMapper      = $indicatorMapper;
        $this->rosettaStone         = $rosettaStone;
        $this->markTypeMapper       = $markTypeMapper;
        $this->gradeGroupMapper     = $gradeGroupMapper;
    }


    /**
     * AJAX method for creating a new capability or indicator
     *
     * @api POST /rubric
     * @since 156
     */
    public function create()
    {
        $post = $this->requestPost;

        // Don't use just folder ID
        $folderId = $post->get('folderId');
        if (!$folderId) {
            $this->sendJSONError('Must supply a folder Id');
            return;
        }
        $folder = $this->folderMapper->findById($folderId);


        // Build a rubric grid
        $assessmentId = $post->getInt('rubricId', 0);
        $rubricRowSet = $this->mapper->retrieveRowSet($folder, false);
        $rubricGrid   = new Grid((new DueWork())->setId($assessmentId), $rubricRowSet);

        // Fill in each level
        $levelCount = $post->getInt('levelCount', 0);
        for ($i = 0; $i < $levelCount; $i++) {
            $rubricGrid->getLevels()->add(new Level());
        }

        $rubricGridTranslator = new RubricGridTranslator();

        if ($post->get('type') === 'indicator') {
            $capabilityId    = $post->get('capabilityId');
            $capabilityName  = $post->get('capabilityName');
            $critName   = $post->get('name');
            if (!$capabilityName) {
                $this->sendJSONError(
                    $this->rosettaStone->translateSentence(
                        'Must supply {:rubricGroup|indef} {:rubricGroup} for new {:rubricCriterion}'
                    )
                );
                return;
            }


            // Only if adding an indicator to an existing capability:
            // Find the capability model, so that the indicator may be saved
            // into the capability (and its folder)
            if ($capabilityId) {
                $capabilityModel = $this->capabilityMapper->findById($capabilityId);
                // Surely this capability is in a folder?
                if ($capabilityModel->getParentType() !== Capability\Model::PARENT_TYPE_CATEGORY) {
                    $this->sendJSONError(
                        $this->rosettaStone->translateSentence(
                            'May only create folder-level {:rubricCriterion|p}'
                        )
                    );
                    return;
                }
                $folderId = $capabilityModel->getParentId();
            }

            $indicatorModels = $this->indicatorMapper->findByColumn('rubric_capability_id', $capabilityId);
            $proposedCapabilityName = Util::normalizeName($capabilityName);
            $proposedName = Util::normalizeName($critName);
            foreach ($indicatorModels as $indicatorModel) {
                $normCapability = Util::normalizeName($indicatorModel->getCapability()->getName());
                $normName = Util::normalizeName($indicatorModel->getName());
                if ($normCapability === $proposedCapabilityName && $normName === $proposedName) {
                    $this->sendJSONError(
                        $this->rosettaStone->translateSentence(
                            '{:rubricCriterion|c} already exists in this {:rubricGroup}'
                        )
                    );
                    return;
                }
            }

            // This indicator needs to be created
            $indicatorModel = $this->indicatorMapper->findOrCreateOneForFolderByKey(
                $folderId,
                $critName,
                isset($capabilityModel) ? $capabilityModel->getName() : $capabilityName
            );

            // Don't have to save anything yet
            $indicator = (new \Schoolbox\Assessment\Rubric\RowSet\Indicator())
                ->setId($indicatorModel->getId())
                ->setName($critName);

            $indicatorData = $rubricGridTranslator->translateIndicator($rubricGrid, $indicator);
            $this->response = $this->responseAsJson([
                // this can differ to $capabilityId if we just had to create the capability to put the indicator in
                'capabilityId' => $indicatorModel->getCapability()->getId(),
                'indicator' => $indicatorData,
            ]);
        } elseif ($post->get('type') === 'capability') {
            // Does the list of indicators/capabilities really need to be saved now?
            // For some reason, capabilities aren't saved whilst criteria are?
            // Probably because capabilities literally couldn't exist w/o criteria
            $capabilityName = $post->get('name');

            // Next step: figure out what gets passed in
            $capability = (new \Schoolbox\Assessment\Rubric\RowSet\Capability())
//                ->setId(rand(1, PHP_INT_MAX))
                ->setName($capabilityName);

            // Ensure the proposed Capability name is not already in use.
            $capabilityModels = $this->capabilityMapper->findForFolder($folderId);
            $proposedName = Util::normalizeName($capabilityName);
            foreach ($capabilityModels as $capabilityModel) {
                $normName = Util::normalizeName($capabilityModel->getName());
                if ($proposedName === $normName) {
                    $this->sendJSONError(
                        $this->rosettaStone->translateSentence(
                            '{:rubricGroup|c} name already exists in this context'
                        )
                    );
                    return;
                }
            }

            $capabilityData = $rubricGridTranslator->translateCapability($rubricGrid, $capability);
            $this->response = $this->responseAsJson($capabilityData);
        }
    }

    /**
     * Sends a JSON error.
     *
     * @param mixed $data
     * @param int $statusCode
     */
    protected function sendJSONError($data, $statusCode = 400)
    {
        $this->response = $this->responseAsJson($data)->withStatus($statusCode);
    }


    /**
     * Return a Twigged shard generated from the file ref given
     *
     * @api POST /rubric/import/{storageTuple}
     * @since 156
     *
     * @param File $file
     */
    public function import(File $file)
    {
        $post = $this->requestPost;

        try {
            if ($assessmentId = (int)$post->get('assessmentId')) {
                $assessment = $this->assessmentDataSource->findById($assessmentId);
                if (!$assessment instanceof DueWork) {
                    throw new UnexpectedValueException('Assessment is not due work: ' . $assessmentId);
                }
            } elseif ($folderId = $post->get('folderId')) {
                $assessment = (new DueWork())
                    ->setId(0)
                    ->setContext($this->folderMapper->findById($folderId));
            } else {
                // can't work with that
                throw new \Schoolbox\Mapper\Exception('No folder or box ID set');
            }

            // Use the mark type and grade group from the form,
            // instead of what is persisted
            $markTypeId = $post->get('markTypeId');
            if ($markTypeId) {
                $assessment->setMarkType($this->markTypeMapper->findById($markTypeId));
            }
            $gradeGroupId = $post->get('gradeGroupId');
            if ($gradeGroupId) {
                $assessment->setGradeGroup($this->gradeGroupMapper->findById($gradeGroupId));
            }

            $instructorMarkTypeId = $post->get('instructorMarkTypeId');
            if ($instructorMarkTypeId) {
                $assessment->setInstructorMarkType($this->markTypeMapper->findById($instructorMarkTypeId));
            }
            $instructorGradeGroupId = $post->get('instructorGradeGroupId');
            if ($instructorGradeGroupId) {
                $assessment->setInstructorGradeGroup($this->gradeGroupMapper->findById($instructorGradeGroupId));
            }

            // attempt to build the grid
            $maxLevels = (int)$this->getDi()->get('config')->get('rubric_max_levels');
            $this->mapper->importGridFromCsv($file, $assessment, $maxLevels);
        } catch (\Exception $e) {
            $this->response = $this->responseAsJson($e->getMessage())->withStatus(400);
            return;
        }

        /* Obtain the grid data separately from the other rubric settings
         *
         * Otherwise you have to infer the settings from what might have been visible in the grid
         */
        $rubricGridTranslator = new RubricGridTranslator();
        $rubricGridPayload = $rubricGridTranslator($assessment->getRubricGrid(), $assessment);

        $this->response = $this->responseAsJson([
            'rubricGrid' => $rubricGridPayload,
        ]);
    }

    /**
     * Download the given rubric as a CSV
     *
     * @api POST /rubric/csv
     * @since 156
     */
    public function download()
    {
        // TODO: there's no permission check against here...
        // TODO: ideally remove the need to provide an assessment at all if we can
        $rubric = $this->requestPost->get('rubricGrid');
        $assessmentId = $this->requestPost->getInt('assessmentId');

        $assessment   = $this->assessmentDataSource->findById($assessmentId);
        if (!$assessment instanceof DueWork) {
            // This is an error. How do I generate one?
            // Note: I can't download rubrics for due works that don't exist yet
            $this->sendError('Can only download rubrics for due work');
            return;
        }

        /* Find the MarkType instance associated with whatever was POSTed
         */
        $markType = null;

        if ($rubric['capabilityMarkType']) {
            try {
                $markType = MarkTypeMapper::getInstance()->findOneByColumn('slug', $rubric['capabilityMarkType']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->sendError('Unknown capability mark type selected');
                return;
            }
        }

        /* Find the Grade Group instance associated with whatever was POSTed
         */
        $gradeGroup = null;

        if ($rubric['capabilityGradeGroup'] !== null) {
            try {
                $gradeGroup = Group\Mapper::getInstance()->findById($rubric['capabilityGradeGroup']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->sendError('Unknown capability grade group selected');
                return;
            }
        }

        $assessment->setCapabilityMarkType($markType)
                   ->setCapabilityGradeGroup($gradeGroup);
        $assessment->setUseMarkRange($rubric['useMarkRange'] ?? false); // TODO: useMarkRange should be against a rubric

        $grid     = (new GridFactory())->buildFromPostData(
            $this->mapper->retrieveGrid($assessment, false),
            [ $assessmentId => $rubric ], // TODO this will probably change as we further implement multi-grid support
            false
        );
        $basename = (new Url($assessment->getTitle()))->getSlug();
        $content  = $this->array2csv((new ArrayExporter())->export($grid));

        $this->response = $this->responseFromContent($content)
            ->withHeader('Pragma', ' ')
            ->withHeader('Cache-Control', ' ')
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Length', strlen($content))
            ->withHeader('Content-Disposition', "attachment; filename=\"$basename-rubric.csv\"")
        ;
    }


    /**
     * Determines whether a rubric submitted via POST will invalidate marks.
     *
     * @api POST /rubric/determineMarkInvalidation
     * @since 181
     */
    public function determineMarkInvalidation(): void
    {
        $post         = $this->requestPost->get('rubric');
        $response = [
            'compatible'    => true,
            'affectedUsers' => [],
        ];

        // TODO: no rubric data? always compatible?
        if (null === $post) {
            $this->response = $this->responseAsJson($response)->withStatus(200);
            return;
        }

        // TODO: multi-rubrics support
        $assessmentId = key($post);


        // Load the current assessment and grid
        try {
            $currentAssessment = $this->assessmentDataSource->findByIdForReview($assessmentId);
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            $this->sendError(
                "Sorry, the activity could not be updated. Please ensure the " .
                "activity still exists and contact your support if the problem persists."
            );
            return;
        }
        if (!$currentAssessment instanceof DueWork) {
            $this->sendError('Can only determine mark invalidation for due work');
            return;
        }
        $currentGrid = $this->mapper->retrieveGrid($currentAssessment, false);


        // Load the proposed assessment and grid
        // Need to load them separately due to object references: if I use the
        // same assessment in both retrieveGrid calls the current grid thinks
        // the current assessment state is the same as the proposed state.
        $proposedAssessment = (clone $currentAssessment);

        // TODO: capabilityMarkType & useMarkRange come from the rubric data itself now, they shouldn't be against the assessment anymore
        $capabilityMarkTypeSlug = $post[$assessmentId]['capabilityMarkType'] ?? MarkType::SLUG_NONE;
        $capabilityMarkType = $this->markTypeMapper->createModel()
            ->setSlug($capabilityMarkTypeSlug)
            ->setId(MarkType::slugToId($capabilityMarkTypeSlug));
        $useMarkRange = $post[$assessmentId]['useMarkRange'] ?? false;

        $proposedAssessment->setCapabilityMarkType($capabilityMarkType);
        $proposedAssessment->setUseMarkRange($useMarkRange);

        $proposedGrid = (new GridFactory())->buildFromPostData(
            $this->mapper->retrieveGrid($proposedAssessment, false),
            $post,
            false
        );


        // Is the proposed grid itself not valid?
        // Doesn't matter for the purpose of this exercise
        if (!$proposedGrid->isCompatibleWith($currentGrid)) {
            $response['compatible'] = false;
            $jsonDecorator = new PropertyDecorator();
            $linkDecorator = new LinkDecorator($this->routeGenerator, $this->authorizer);

            // Determine who's actually affected?
            foreach ($currentAssessment->getAllParticipants() as $participant) {
                $user   = $participant->getUser();
                $userId = $user->getId();

                // Instructor-given feedback
                if ($this->mapper->hasInstructorMarkedGrid($participant)) {
                    $response['affectedUsers'][$userId]['instructorGiven'] = true;
                }

                // Self-assessment feedback
                if ($currentAssessment->isSelfAssessable() && $this->mapper->hasSelfAssessedMarkedGrid($participant)) {
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
        $this->response = $this->responseAsJson($response)->withStatus(200);
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
            $row = array_map(fn ($x) => trim($x ?? ''), $row);
            fputcsv($fp, $row, escape: '');
        }
        rewind($fp);
        $result = stream_get_contents($fp);
        fclose($fp);
        return $result;
    }
}
