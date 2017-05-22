<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Feedback;

use Alaress\Web\Controller\Base;
use Aura\Web\Request\Values as RequestValues;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\MarkMapper as RubricMarkMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends Base
{

    /**
     * @var IFeedbackDataSource
     */
    protected $feedbackDataSource;


    /**
     * Controller constructor.
     *
     * @param IFeedbackDataSource $feedbackDataSource
     */
    public function __construct(IFeedbackDataSource $feedbackDataSource)
    {
        $this->feedbackDataSource = $feedbackDataSource;
    }

    /**
     * Post into the feedback collection for a participant. This will create "floating feedback"
     *
     * This is a dueWork-only route. It is not applicable to Project, Quiz, or Task.
     *
     * @api   POST /learning/assessments/{assessmentId}/{studentId}/feedback
     * @level 165
     *
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
        catch (\RangeException $e) {
            $this->sendError('Invalid mark provided');
            return;
        }
        catch (MapperException $e) {
            $this->sendError('Invalid mark provided');
            return;
        }
        catch (\PDOException $e) {
            $this->sendError('Unable to save results');
        }
    }

    /**
     * Process any rubric data attached to the Feedback
     *
     * @param Participant $participant
     * @param $post
     */
    protected function processRubric(Participant $participant, $post)
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
            $this->processRubric($participant, $this->getRequest()->post);
        }
        catch (\RangeException $e) {
            $this->sendError('Invalid mark provided');
            return;
        }
        catch (\Schoolbox\Mapper\Exception $e) {
            $this->sendError('Invalid mark provided');
            return;
        }
        catch (\PDOException $e) {
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
    }

    /**
     * @api   DELETE /assessments/:assessmentId/feedback/:feedback
     * @level 165
     * @param IFeedback $feedback
     */
    public function deleteAssessmentFeedback(IFeedback $feedback)
    {
        $code = 204;
        $data = null;

        try {
            $this->getFeedbackDataSource()->delete($feedback);

        } catch (\Schoolbox\Mapper\Exception $e) {
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
