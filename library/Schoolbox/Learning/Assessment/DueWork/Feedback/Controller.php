<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Alaress\Web\Controller\Base;
use Aura\Web\Request\Values as RequestValues;
use PDOException;
use RangeException;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfAssessable;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\UserMapper;
use Alaress_Date_Format;

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

    /** @var Rubric\RubricMapper */
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
     * @param Rubric\RubricMapper   $rubricMapper
     * @param ReturnedWorkMapper    $returnedWorkMapper
     * @param Plagscan\Mapper       $plagscanMapper
     */
    public function __construct(
        IFeedbackDataSource $feedbackDataSource,
        ParticipantDataSource $participantDataSource,
        AssessmentDataSource $assessmentDataSource,
        ResponseDataSource $responseDataSource,
        UserMapper $userMapper,
        Rubric\RubricMapper $rubricMapper,
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
     * @level 165
     * @param Participant $participant
     */
    public function postParticipantFeedback(Participant $participant)
    {
        $mostRecentFeedback = $participant->findMostRecentFeedback();
        $post               = $this->getRequest()->post->getArrayCopy();

        try {
            $feedback = $this->processFeedback($participant, $mostRecentFeedback, $post);

            // Don't process rubric; just copy from the last one
            $assessment = $participant->getAssessable();
            if (
                $feedback instanceof DueWork\Feedback\Model
                && $mostRecentFeedback instanceof DueWork\Feedback\Model
                && $assessment instanceof DueWork\Model
                && $assessment->getUseRubric()
            ) {
                $this->rubricMapper->copySelections($mostRecentFeedback->getId(), $feedback->getId());
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
     * @level 170
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
     * @param Participant $participant
     * @param array       $post
     * @param IFeedback   $feedback
     */
    protected function processRubric(Participant $participant, array $post, IFeedback $feedback)
    {
        if(!$participant->getAssessable() instanceof DueWork\Model){
            return;
        }

        // Did we just give some rubric marks?
        if (is_array($post['rubric'])) {
            $this->rubricMapper->saveForFeedback($post['rubric']['descriptors'], $feedback->getId());
        }
    }

    /**
     * Create a Feedback that is associated with a particular Response
     *
     * @api POST /learning/assessments/{assessmentId}/{studentId}/{responseId}/feedback
     * @param Participant $participant
     * @param DueWork\Response\Model $response
     * @throws \Alaress\Web\Controller\Exception
     */
    public function postResponseFeedback(Participant $participant, DueWork\Response\Model $response)
    {
        $mostRecentFeedback = $participant->findMostRecentFeedback();

        try {
            $feedback = $this->processFeedback($participant, $mostRecentFeedback, $this->getRequest()->post, $response);
            $this->processRubric($participant, $this->getRequest()->post->getArrayCopy(), $feedback);

            /* If this is a self-assessment or self reflection, also set submitted_at on the Response
             * when publishing the Feedback itself
             */
            $selfAssessed = (new ValidSelfAssessable())->isSatisfiedBy($this->getUser(), $participant);

            if ($selfAssessed && !$response->isSubmitted() && $this->getRequest()->post->get('publish')) {
                $response->setSubmittedAt(\Alaress_Date_Format::now());

                $this->responseDataSource->save($response);

                /* Save all the plagscan stubs and records too
                 * But this is a special flow just for self-reflection
                 * Originated from Assessment/DueWork/Controller postCreateResponse()
                 */
                $plagscanMapper = $this->plagscanMapper;

                foreach ($response->getAllFiles() as $currentFile) {
                    if (!$currentFile->getEditableInline()) {
                        $plagscanMapper->saveNewPlagscanStub($currentFile);
                    }
                }

                $this->getResponse()->content->set([
                    'format' => 'json',
                    'data'   => [],
                    'postOutput' => [
                        function() use ($plagscanMapper, $response) {
                            if ($response->getId()) {
                                foreach ($response->getAllFiles() as $currentFile) {
                                    $filename = $plagscanMapper->plagscanFilename($currentFile);

                                    $plagscanMapper->saveNewPlagscan($currentFile, $filename);
                                }
                            }
                        }
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
     * @param Participant         $participant
     * @param IFeedback|null      $mostRecentFeedback
     * @param RequestValues|array $postVars
     * @param IResponse|null      $response
     * @return DueWork\Feedback\Model
     * @throws \Alaress\Web\Controller\Exception
     */
    private function processFeedback(Participant $participant, IFeedback $mostRecentFeedback = null, $postVars, IResponse $response = null)
    {
        $hasRubric = array_key_exists('rubric', $postVars);

        $dueWorkFeedbackFactory = new DueWork\Feedback\Factory(
            $this->getFeedbackDataSource(),
            $participant,
            $this->getUser(),
            $mostRecentFeedback,
            $postVars['mark'],
            $postVars['comment'],
            $postVars['publish'],
            $postVars['attachment'],
            $hasRubric,
            $response
        );

        $feedback = $dueWorkFeedbackFactory->produce();
        $this->getFeedbackDataSource()->save($feedback);

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
     * @level 165
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

}
