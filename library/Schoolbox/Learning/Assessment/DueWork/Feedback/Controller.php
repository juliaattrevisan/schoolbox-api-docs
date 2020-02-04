<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use PDOException;
use RangeException;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfAssessable;
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfGradable;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;

class Controller extends Base
{

    /** @var IFeedbackDataSource */
    protected $feedbackDataSource;

    /** @var ParticipantDataSource */
    protected $participantDataSource;

    /** @var AssessmentDataSource */
    protected $assessmentDataSource;

    /** @var ResponseDataSource */
    protected $responseDataSource;

    /** @var UserMapper */
    protected $userMapper;

    /** @var RubricDataSource */
    private $rubricMapper;

    /** @var ReturnedWorkMapper */
    private $returnedWorkMapper;

    /** @var Plagscan\Mapper */
    private $plagscanMapper;

    /**
     * Controller constructor.
     *
     * @param IFeedbackDataSource   $feedbackDataSource
     * @param ParticipantDataSource $participantDataSource
     * @param AssessmentDataSource  $assessmentDataSource
     * @param ResponseDataSource    $responseDataSource
     * @param UserMapper            $userMapper
     * @param RubricDataSource      $rubricMapper
     * @param ReturnedWorkMapper    $returnedWorkMapper
     * @param Plagscan\Mapper       $plagscanMapper
     */
    public function __construct(
        IFeedbackDataSource $feedbackDataSource,
        ParticipantDataSource $participantDataSource,
        AssessmentDataSource $assessmentDataSource,
        ResponseDataSource $responseDataSource,
        UserMapper $userMapper,
        RubricDataSource $rubricMapper,
        ReturnedWorkMapper $returnedWorkMapper,
        Plagscan\Mapper $plagscanMapper
    ) {
        $this->feedbackDataSource    = $feedbackDataSource;
        $this->participantDataSource = $participantDataSource;
        $this->assessmentDataSource  = $assessmentDataSource;
        $this->responseDataSource    = $responseDataSource;
        $this->userMapper            = $userMapper;
        $this->rubricMapper          = $rubricMapper;
        $this->returnedWorkMapper    = $returnedWorkMapper;
        $this->plagscanMapper        = $plagscanMapper;
    }

    /**
     * Post into the feedback collection for a participant. This will create "floating feedback"
     *
     * This is a dueWork-only route. It is not applicable to Project, Quiz, or Task.
     *
     * @api   POST /learning/assessments/{assessmentId}/{studentId}/feedback
     * @since 165
     * @param DueWork\Model $assessment
     * @param User          $student
     */
    public function postParticipantFeedback(DueWork\Model $assessment, User $student)
    {
        $participant        = $assessment->getParticipant($student);
        $reviewRoute        = $this->determineReviewRoute($participant);
        $post               = $this->getRequest()->post->getArrayCopy();
        $isSelfAssessment   = $reviewRoute !== ReturnedWork::REVIEW_ROUTE_INSTRUCTOR;
        $mostRecentFeedback = $isSelfAssessment
            ? $participant->findMostRecentSelfAssessedFeedback()
            : $participant->findMostRecentInstructorGivenFeedback();

        $mostRecentInstructorGivenFeedback = $participant->findMostRecentInstructorGivenFeedback();

        try {
            $feedback = $this->processFeedback(
                $participant,
                $post,
                $reviewRoute,
                $mostRecentFeedback,
                ($mostRecentInstructorGivenFeedback !== null) ? $mostRecentInstructorGivenFeedback->getResponse() : null
            );

            // Try to process a rubric.
            // If there was none, copy it from the last feedback
            if (!$this->processRubric($participant, $post, $feedback)) {
                if ($feedback instanceof DueWork\Feedback\Model
                    && $mostRecentFeedback instanceof DueWork\Feedback\Model
                    && $assessment instanceof DueWork\Model
                    && $assessment->getUseRubric()
                    && $feedback->getId() != $mostRecentFeedback->getId()
                ) {
                    $this->rubricMapper->copySelections($mostRecentFeedback->getId(), $feedback->getId());
                }
            }
        }
        catch (RangeException $e) {
            $this->sendError('Invalid mark provided');
            return;
        }
        catch (MapperException $e) {
            $this->sendError('Invalid mark provided');
            return;
        }
        catch (PDOException $e) {
            $this->sendError('Unable to save results');
        }
    }


    /**
     * Submit multiple feedbacks in single call
     *
     * This is a dueWork-only route
     *
     * @param DueWork\Model $assessment
     * @api   POST /learning/assessments/{assessmentId}/feedbacks
     * @since 170
     *
     */
    public function postParticipantFeedbacks(DueWork\Model $assessment)
    {
        /** @var int[] $draftFeedbackIds */
        $draftFeedbackIds = $this->getRequest()->post->get('drafts', []);
        $now              = Alaress_Date_Format::now();
        $results = [];

        foreach ($draftFeedbackIds as $draftFeedbackId) {
            // Verify that this return is for this assessment
            $submissionReturn = $this->returnedWorkMapper->findById($draftFeedbackId);
            $publishSuccess   = false;

            if ($submissionReturn->getSubmissionBox()->getAssessmentModel()->getId() === $assessment->getId()) {
                $submissionReturn->setPublishedAt($now);

                try{
                    $this->returnedWorkMapper->save($submissionReturn);
                    $publishSuccess = true;
                } catch (PDOException $e){
                    $publishSuccess = false;
                }

            }

            $results[$draftFeedbackId] = ['publishSuccess'   => $publishSuccess];
        }

        $this->getResponse()->content->set(
            $this->buildJsonOutput($results)
        );
    }

    /**
     * Process any rubric data attached to the Feedback
     *
     * TODO SA-6627: validate selected descriptor value
     *
     * @param Participant $participant
     * @param array       $post
     * @param IFeedback   $feedback
     * @return bool Was a rubric processed?
     */
    protected function processRubric(Participant $participant, array $post, IFeedback $feedback): bool
    {
        if (!$participant->getAssessable() instanceof DueWork\Model){
            return false;
        }

        // Presumably $feedback has been saved already?
        $factory = new \Schoolbox\Assessment\Rubric\MarkedGrid\Factory();

        // Did we just give some rubric marks? Save them
        if (\is_array($post['rubric'])) {
            $markedGrid = $factory->buildFromPostData(
                $this->rubricMapper->retrieveMarkedGrid($feedback),
                $post['rubric']
            );

            $this->rubricMapper->persistMarkedGrid($markedGrid);
            return true;
        }
        // If from inline grader, save empty marks
        if (array_key_exists('fromInlineGrader', $post) &&
            $post['fromInlineGrader'] &&
            $feedback->getAssessable()->getUseRubric()
        ) {
            $markedGrid = $factory->buildFromPostData(
                $this->rubricMapper->retrieveMarkedGrid($feedback),
                []
            );

            $this->rubricMapper->persistMarkedGrid($markedGrid);
            return true;
        }

        // Neither: do nothing (and copy from previous feedback)
        return false;
    }


    /**
     * Create a Feedback that is associated with a particular Response
     *
     * @api POST /learning/assessments/{assessmentId}/{studentId}/{responseId}/feedback
     * @param DueWork\Model          $assessment
     * @param User                   $student
     * @param DueWork\Response\Model $response
     * @throws \Alaress\Web\Controller\Exception
     *
     * @throws UnexpectedValueException If due work model is not sufficiently hydrated
     */
    public function postResponseFeedback(DueWork\Model $assessment, User $student, DueWork\Response\Model $response)
    {
        $participant        = $assessment->getParticipant($student);
        $reviewRoute        = $this->determineReviewRoute($participant);
        $post               = $this->getRequest()->post->getArrayCopy();
        $isSelfAssessment   = $reviewRoute !== ReturnedWork::REVIEW_ROUTE_INSTRUCTOR;
        $mostRecentFeedback = $isSelfAssessment
            ? $participant->findMostRecentSelfAssessedFeedback()
            : $participant->findMostRecentInstructorGivenFeedback();

        try {
            $feedback = $this->processFeedback($participant, $post, $reviewRoute, $mostRecentFeedback, $response);
            $this->processRubric($participant, $post, $feedback);

            if ($isSelfAssessment && !$response->isSubmitted() && $this->getRequest()->post->get('publish')) {
                $response->setSubmittedAt(\Alaress_Date_Format::now());

                $this->responseDataSource->save($response);

                /* Save all the plagscan stubs and records too
                 * But this is a special flow just for self-reflection
                 * Originated from Assessment/DueWork/Controller postCreateResponse()
                 */
                $plagscanMapper = $this->plagscanMapper;

                if ($assessment->getSimilarityCheckEnabled()) {
                    foreach ($response->getAllFiles() as $currentFile) {
                        if (!$currentFile->getEditableInline()) {
                            $plagscanMapper->saveNewPlagscanStub($currentFile);
                        }
                    }
                }

                $this->getResponse()->content->set([
                    'format' => 'json',
                    'data'   => [],
                    'postOutput' => [
                        function () use ($plagscanMapper, $response, $assessment) {
                            if ($assessment->getSimilarityCheckEnabled()) {
                                foreach ($response->getAllFiles() as $currentFile) {
                                    $filename = $plagscanMapper->plagscanFilename($currentFile);

                                    $plagscanMapper->saveNewPlagscan($currentFile, $filename);
                                }
                            }
                        },
                    ],
                ]);
            }
        }
        catch (RangeException $e) {
            $this->sendError('Invalid mark provided');
            return;
        }
        catch (MapperException $e) {
            $this->sendError('Invalid mark provided');
            return;
        }
        catch (PDOException $e) {
            $this->sendError('Unable to save results');
        }
    }


    /**
     * Process feedback
     *
     * This method is shared between the Quickmark (postParticipantFeedback) and the
     * Inline Grader (postResponseFeedback) methods so they behave similarly
     *
     * @param Participant    $participant
     * @param array          $postVars
     * @param string         $reviewRoute By which mechanism is this feedback being generated?
     * @param IFeedback|null $mostRecentFeedback
     * @param IResponse|null $response
     * @return DueWork\Feedback\Model
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    private function processFeedback(
        Participant $participant,
        array $postVars,
        string $reviewRoute,
        IFeedback $mostRecentFeedback = null,
        IResponse $response = null
    ):Model {

        // Check whether new rubric data and/or attachments are being passed through
        $fromInlineGrader = array_key_exists('fromInlineGrader', $postVars);
        $hasRubric        = array_key_exists('rubric', $postVars);
        // Check whether the latest feedback actually has rubric data set.
        if ($mostRecentFeedback !== null
            && $mostRecentFeedback->getAssessable() instanceof DueWork\Model
            && $mostRecentFeedback->getAssessable()->getUseRubric()
        ) {
            $hasRubric = true;
        }

        $dueWorkFeedbackFactory = new DueWork\Feedback\Factory(
            $this->getFeedbackDataSource(),
            $participant,
            $this->getUser(),
            $postVars['mark'],
            (bool)($postVars['resubmissionRequired'] ?? false),
            $postVars['comment'],
            $postVars['publish'],
            $postVars['attachment'],
            $fromInlineGrader,
            $reviewRoute,
            $mostRecentFeedback,
            $response instanceof DueWork\Response\Model ? $response : null
        );

        $feedback      = $dueWorkFeedbackFactory->produce();
        $isNewFeedback = $feedback->getId() === null;
        if (!$hasRubric && !$isNewFeedback && $feedback->isEmpty()) {
            $this->getFeedbackDataSource()->delete($feedback);
        } else {
            $this->getFeedbackDataSource()->save($feedback);
        }

        $data = [
            'success' => true,
            'message' => 'OK',
            'id'      => $feedback->getId(),
        ];

        $this->respond('', $data);
        return $feedback;
    }

    /**
     * @api   DELETE /assessments/:assessmentId/feedback/:feedback
     * @since 165
     * @param IFeedback $feedback
     */
    public function deleteAssessmentFeedback(IFeedback $feedback)
    {
        $code = 204;
        $data = [];

        try {
            $this->getFeedbackDataSource()->delete($feedback);

        } catch (MapperException $e) {
            $code = 400;
            $data = $e->getMessage();
        }

        $this->respond('', $data, $code);
    }


    /**
     * @param string       $template
     * @param array|string $data
     * @param int          $responseCode
     */
    protected function respond($template, $data, $responseCode = 200)
    {
        $this->getResponse()->status->set($responseCode);
        $this->getResponse()->content->set($this->buildContent($template, $data));
    }


    /**
     * @return IFeedbackDataSource
     */
    protected function getFeedbackDataSource()
    {
        return $this->feedbackDataSource;
    }


    /**
     * What's the review route?
     *
     * @param Participant $participant
     * @return string
     */
    private function determineReviewRoute(Participant $participant): string
    {
        $reviewRoute      = ReturnedWork::REVIEW_ROUTE_INSTRUCTOR;
        $isSelfAssessment = (new ValidSelfAssessable())->isSatisfiedBy($this->getUser(), $participant);
        /* TODO - this has a known bug - see SA-5980;
         * Consider case where:
         * * Assessment is self-assessable,
         * * User is an Instructor
         * * User is a participant
         * * User is looking at their own inline grader page via normal marking workflow
         * This currently returns a false positive.
         */
        if ($isSelfAssessment) {
            $reviewRoute = (new ValidSelfGradable())->isSatisfiedBy($this->getUser(), $participant)
                ? ReturnedWork::REVIEW_ROUTE_SELF_GRADING
                : ReturnedWork::REVIEW_ROUTE_SELF_REFLECTION;
        }
        return $reviewRoute;
    }
}
