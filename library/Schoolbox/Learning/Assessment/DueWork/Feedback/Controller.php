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
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\MarkMapper as RubricMarkMapper;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

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

    /**
     * Controller constructor.
     *
     * @param IFeedbackDataSource $feedbackDataSource
     * @param ParticipantDataSource $participantDataSource
     * @param AssessmentDataSource $assessmentDataSource
     * @param ResponseDataSource $responseDataSource
     * @param UserMapper $userMapper
     */
    public function __construct(
        IFeedbackDataSource $feedbackDataSource,
        ParticipantDataSource $participantDataSource,
        AssessmentDataSource $assessmentDataSource,
        ResponseDataSource $responseDataSource,
        UserMapper $userMapper
    ) {
        $this->feedbackDataSource = $feedbackDataSource;
        $this->participantDataSource = $participantDataSource;
        $this->assessmentDataSource = $assessmentDataSource;
        $this->responseDataSource = $responseDataSource;
        $this->userMapper = $userMapper;
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
        $post = $this->getRequest()->post->getArrayCopy();

        try {
            $this->processFeedback($participant, $mostRecentFeedback, $post);
            $this->processRubric($participant, $post);
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
     * @api   POST /learning/assessments/feedbacks
     * @level 165
     */
    public function postParticipantFeedbacks()
    {
        $results = [];

        $post = $this->getRequest()->post->getArrayCopy();

        foreach ($post['feedback'] as $feedback) {
            $student = $this->userMapper->findById($feedback['studentId']);
            $assessment = $this->assessmentDataSource->findById($feedback['assessmentId']);
            $this->participantDataSource->loadParticipationIntoAssessment($assessment, $student);
            $participant = $assessment->getParticipant($student);
            $response = $this->responseDataSource->findForAssessment($assessment, $student);
            $mostRecentFeedback = $participant->findMostRecentFeedback();

            $formData = [
                'response' => $response,
                'publish' => '1'
            ];

            foreach ($feedback['formData'] as $data) {
                $formData[$data['name']] = $data['value'];
            }

            try {
                $feedback = $this->processFeedback($participant, $mostRecentFeedback, $formData);
                $this->processRubric($participant, $formData);

                $results[] = [
                    'id'                => $feedback->getId(),
                    'participantId'     => $participant->getUser()->getId(),
                    'success'           => true,
                    'submittedLate'     => $assessment->isOverdue() || $assessment->isPastClose()
                ];
            }
            catch (RangeException $e) {
                $results[] = [
                    'participantId'     => $participant->getUser()->getId(),
                    'success'           => false,
                    'message'           => 'Invalid mark provided for ' . $participant->getUser()->getFullName() . ". "
                ];
            }
            catch (MapperException $e) {
                $results[] = [
                    'participantId'     => $participant->getUser()->getId(),
                    'success'           => false,
                    'message'           => 'Invalid mark provided for ' . $participant->getUser()->getFullName() . ". "
                ];
            }
            catch (PDOException $e) {
                $results[] = [
                    'participantId'     => $participant->getUser()->getId(),
                    'success'           => false,
                    'message'           => 'Unable to save results for ' . $participant->getUser()->getFullName() . ". "
                ];
            }
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
     */
    protected function processRubric(Participant $participant, array $post)
    {
        if(!$participant->getAssessable() instanceof DueWork\Model){
            return;
        }
        /** @var DueWork\Model $dueWork */
        $dueWork = $participant->getAssessable();

        $isDraft = !isset($post['publish']) || !$post['publish'];

        // Did we just give some rubric marks?
        if (is_array($post['rubric'])) {
            $rubMap = RubricMapper::getInstance();
            $rubMap->saveForParticipant($participant, $post['rubric']['descriptors'], $isDraft);
        }
        // Else did we just publish draft marks via the status page?
        elseif(!$isDraft){
            RubricMarkMapper::getInstance()->publishDraftMarks($participant->getUser(), $dueWork);
        }
    }

    /**
     * Create a Feedback that is associated with a particular Response
     *
     * @api POST /learning/assessments/{assessmentId}/{studentId}/{responseId}/feedback
     * @param Participant $participant
     * @param IResponse $response
     * @throws \Alaress\Web\Controller\Exception
     */
    public function postResponseFeedback(Participant $participant, IResponse $response)
    {
        $mostRecentFeedback = $participant->findMostRecentFeedback();

        try {
            $this->processFeedback($participant, $mostRecentFeedback, $this->getRequest()->post, $response);
            $this->processRubric($participant, $this->getRequest()->post->getArrayCopy());
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
