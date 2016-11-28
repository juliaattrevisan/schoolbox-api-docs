<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Feedback;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use DueWork_ErrorMapper;
use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\Feedback\File\Model as DueWorkFeedbackFile;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

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
     * TODO evaluate the applicability of this to Quiz and Task. It is not applicable to Project.
     *
     * @api   POST /learning/assessments/{assessmentId}/{studentId}/feedback
     * @level 165
     *
     * @param Participant $participant
     */
    public function postParticipantFeedback(Participant $participant)
    {
        $feedbacks = $this->getFloatingFeedback($participant);

        try {
            $this->processFeedback($participant, $feedbacks);
        }
        catch (\RangeException $e) {
            $this->sendError('Invalid mark given');
            return;
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
        $feedbacks  = new Collection();
        $mostRecent = $participant->findMostRecentFeedback();
        if (isset($mostRecent)) {
            $feedbacks->add($mostRecent);
        }

        try {
            $this->processFeedback($participant, $feedbacks, $response);
        }
        catch (\RangeException $e) {
            $this->sendError('Invalid mark given');
            return;
        }
    }

    /**
     * Process feedbacks
     *
     * This method is shared between the Quickmark (postParticipantFeedback) and the
     * Inline Grader (postResponseFeedback) methods so they behave similarly
     *
     * @param Participant $participant
     * @param Collection $feedbacks
     * @param IResponse $response
     * @throws \Alaress\Web\Controller\Exception
     */
    private function processFeedback(Participant $participant, Collection $feedbacks, IResponse $response = null)
    {
        if ($feedbacks->count() == 0 || $feedbacks->first()->isPublished()) {
            // if none, or published: create new draft
            $feedback = $this->getFeedbackDataSource()->createModel($participant->getAssessable());

            $feedback
                ->setReviewee($participant->getUser())
                ->setCreatedAt(Alaress_Date_Format::now())
            ;

            // on CREATE, return 201 Created
            $responseCode = 201;

        } else {
            // most recent is draft, use that
            $feedback = $feedbacks->first();

            // on UPDATE, return 204 No Content
            $responseCode = 204;
        }

        // mutate model found, and save
        $postMark    = trim($this->getRequest()->post['mark']);
        $postComment = trim($this->getRequest()->post['comment']);
        $postPublish = $this->getRequest()->post['publish'];

        /** @var IFeedback $feedback */
        $feedback
            ->setReviewer($this->getUser())
            ->setUpdatedAt(Alaress_Date_Format::now())
        ;

        if ($postComment) {
            // comment defined and non-empty
            $feedback->setComment($postComment);
        }

        if ($postMark) {
            // mark defined and non-empty
            try {

                $scheme = $participant->getAssessable()->getMarkScheme();

                // verify normalisation, then sanitise the output
                $norm = $scheme->getNormalisedMark($postMark);
                $sane = $scheme->getHumanReadableMark($norm);

                $feedback
                    ->setNormalisedMark($norm)
                    ->setHumanReadableMark($sane)
                ;
            } catch (InvalidArgumentException $e) {
                // check for error code
                try {
                    $code = DueWork_ErrorMapper::getInstance()->findOneByColumn('display_as', $postMark);

                    $feedback
                        ->setNormalisedMark($code->getNormaliseAs())
                        ->setHumanReadableMark($postMark)
                    ;

                } catch (\Schoolbox\Mapper\Exception $e) {
                    // invalid input
                    $this->sendError('Invalid mark given');
                    return;
                }
            }
        }

        // Attach any attached files (if this kind of attachment accepts them)
        $feedbackFileDecorator = new \Schoolbox\Learning\Assessment\FeedbackFileDecorator($feedback, $this->getRequest()->post['attachment']);
        $feedbackFileDecorator->decorate();

        if ($postPublish == true) {
            // received signal to publish this
            if (!$feedback->isPublished()) {
                $feedback->setPublishedAt(Alaress_Date_Format::now());
            }
        }

        /* Attach feedback to a particular Response instance
         */
        if ($response) {
            $feedback->setResponse($response);
        }

        $this->getFeedbackDataSource()->save($feedback);

        $template = '';
        $data = [
            'success' => true,
            'message' => 'OK',
            'id'      => $feedback->getId(),
        ];

        $this->respond($template, $data, $responseCode);
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
     * Retrieve the floating feedback for this participant, IN REVERSE CHRONOLOGICAL ORDER
     *
     * @param Participant $participant
     * @return Collection|IFeedback[]
     */
    protected function getFloatingFeedback(Participant $participant)
    {
        // obtain a reference to the most recent floating feedback
        // TODO it would be nice to do this via SQL rather than in here, but them's the breaks
        return $this->getFeedbackDataSource()
            ->findForAssessment($participant->getAssessable())
            ->filter(
                function (IFeedback $feedback) use ($participant)
                {
                    if (!empty($feedback->getResponse()->getId())) {
                        // floating feedback only
                        return false;
                    }

                    // filter to current user only
                    return $participant->getUser()->getId() == $feedback->getReviewee()->getId();
                }
            )
            ->sortBy('updatedAt', 'desc')
        ;

    }

    /**
     * @param string $template
     * @param array  $data
     * @param int    $responseCode
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
