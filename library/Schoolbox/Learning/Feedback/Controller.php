<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Feedback;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Participant;

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
        // obtain a reference to the most recent floating feedback
        // TODO it would be nice to do this via SQL rather than in here, but them's the breaks
        $feedbacks = $this->getFeedbackDataSource()
            ->findForAssessment($participant->getAssessable())
            ->filter(
                function (IFeedback $feedback) use ($participant)
                {
                    if ($feedback->getResponse()) {
                        // floating feedback only
                        return false;
                    }

                    // filter to current user only
                    return $participant->getUser()->getId() == $feedback->getReviewee()->getId();
                }
            )
            ->sortBy('updatedAt', 'desc')
        ;

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
        $postMark    = $this->getRequest()->post['mark'];
        $postComment = $this->getRequest()->post['comment'];

        $feedback
            ->setReviewer($this->getUser())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setComment($postComment)
            ->setHumanReadableMark($postMark)
            ->setNormalisedMark($participant->getAssessable()->getMarkScheme()->getNormalisedMark($postMark))
        ;

        $this->getFeedbackDataSource()->save($feedback);

        $template = '';
        $data = [];

        $this->respond($template, $data, $responseCode);
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
     * @param string $template
     * @param array  $data
     * @return \Alaress\Web\Output\Base
     */
    protected function buildContent($template, array $data)
    {
        if (strpos($this->getRequest()->headers->get('accept'), 'application/json') !== false) {
            // prefer JSON if specified... this isn't exactly correct, but it's close enough for now
            return $this->buildJsonOutput($data);

        } elseif ($this->getRequest()->isXhr()) {
            // if the request is AJAX, don't return the chrome
            return $this->buildUnthemedHtmlOutput($template, $data);

        } else {
            // full synchronous page
            return $this->buildHtmlOutput($template, $data);
        }
    }


    /**
     * @return IFeedbackDataSource
     */
    protected function getFeedbackDataSource()
    {
        return $this->feedbackDataSource;
    }

}
