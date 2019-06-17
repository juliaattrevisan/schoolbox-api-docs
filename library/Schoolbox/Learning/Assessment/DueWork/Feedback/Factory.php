<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Schoolbox\Learning\Assessment\Feedback\FileDecorator;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\IFeedback;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\Participant;
use \Schoolbox\Learning\MarkScheme\IMarkScheme;
use \Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkResponse;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\Feedback\SubSource\DueWork as DueWorkFeedbackDataSource;

class Factory
{

    /**
     * @var DueWorkFeedbackDataSource
     */
    private $dueWorkFeedbackDataSource;

    /**
     * @var Participant
     */
    private $participant;

    /**
     * Currently logged-in user
     * Usually a teacher
     *
     * @var User
     */
    private $currentUser;

    /**
     * Most recent existing feedback for the participation
     *
     * @var DueWorkFeedback|null
     */
    private $mostRecentFeedback;

    /**
     * @var string
     */
    private $mark;

    /**
     * @var string
     */
    private $comment;

    /**
     * @var string
     */
    private $publish;

    /**
     * An array of attachzone attachments
     *
     * @var array
     */
    private $files;

    /**
     * Does this updated from inline grader
     *
     * @var bool
     */
    private $fromInlineGrader;

    /**
     * By which work-flow was the feedback created
     *
     * @var string
     */
    private $reviewRoute;

    /**
     * @var DueWorkResponse
     */
    private $response;


    /**
     * Factory constructor.
     *
     * @param IFeedbackDataSource|DueWorkFeedbackDataSource $dueWorkFeedbackDataSource
     * @param Participant $participant
     * @param User $currentUser
     * @param string $mark
     * @param string $comment
     * @param string $publish one of ['0'|'1']
     * @param array $files
     * @param bool $fromInlineGrader
     * @param string $reviewRoute
     * @param IFeedback|DueWorkFeedback|null $mostRecentFeedback
     * @param DueWorkResponse|null $response
     */
    public function __construct(
        IFeedbackDataSource $dueWorkFeedbackDataSource,
        Participant $participant,
        User $currentUser,
        $mark,
        $comment,
        $publish,
        $files,
        $fromInlineGrader,
        string $reviewRoute,
        IFeedback $mostRecentFeedback = null,
        DueWorkResponse $response = null
    ) {
        $this->dueWorkFeedbackDataSource = $dueWorkFeedbackDataSource;
        $this->participant               = $participant;
        $this->currentUser               = $currentUser;
        $this->mostRecentFeedback        = $mostRecentFeedback;
        $this->mark                      = $mark;
        $this->comment                   = $comment;
        $this->publish                   = $publish;
        $this->files                     = $files;
        $this->fromInlineGrader             = $fromInlineGrader;
        $this->reviewRoute               = $reviewRoute;
        $this->response                  = $response;
    }

    /**
     * Produce a DueWork Feedback with the assorted details in it
     * such as mark, comment, publish, authors and attachments
     *
     * @return DueWorkFeedback
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function produce(): DueWorkFeedback
    {
        // Is this the first feedback for this participant, or is most recent feedback already published ?
        if (is_null($this->mostRecentFeedback) || $this->mostRecentFeedback->isPublished()) {

            // create new feedback
            /** @var DueWorkFeedback $feedback */
            $feedback = $this->dueWorkFeedbackDataSource->createModel($this->participant->getAssessable())
                ->setReviewee($this->participant->getUser())
                ->setCreatedAt(\Alaress_Date_Format::now());
            $this->participant->getAllFeedbacks()->add($feedback);
        } else {
            // most recent is draft, use that
            $feedback = $this->mostRecentFeedback;
        }

        // mutate model found, and save
        $postMark        = $this->mark ? trim($this->mark) : null;
        $postComment     = $this->comment ? trim($this->comment) : null;
        $postReviewRoute = $this->reviewRoute ? trim($this->reviewRoute) : null;
        $postPublish     = $this->publish ? trim($this->publish) : null;

        /** @var DueWorkFeedback $feedback */
        $feedback
            ->setReviewer($this->currentUser)
            ->setReviewRoute($postReviewRoute)
            ->setUpdatedAt(\Alaress_Date_Format::now())
            ->setComment($postComment);

        if ($postMark !== '') {

            // mark defined and non-empty
            try {
                /** @var IMarkScheme $scheme */
                $scheme = $this->participant->getAssessable()->getMarkScheme();

                // verify normalisation, then sanitise the output
                $norm = $scheme->getNormalisedMark($postMark);
                $humanReadableMark = $scheme->getHumanReadableMark($norm);

                $feedback
                    ->setNormalisedMark($norm)
                    ->setHumanReadableMark($humanReadableMark);
            }
            catch (\InvalidArgumentException $e) {
                $code = \DueWork_ErrorMapper::getInstance()->findOneByColumn('display_as', $postMark);

                $feedback
                    ->setNormalisedMark($code->getNormaliseAs())
                    ->setHumanReadableMark($postMark);
            }
        }
        else {
            // TODO what on EARTH is going on here? what is this "special case"?

            //If mark is set to empty string this is a special case
            //TODO potentially the mark post var may not have been set, we may lose the mark in this case so we should
            //test the existence of the post var as well

            $feedback
                ->setNormalisedMark(null)
                ->setHumanReadableMark('');
        }

        // Attach any attached files (if this kind of attachment accepts them)
        if ($this->fromInlineGrader) {
            $feedbackFileDecorator = new FileDecorator($feedback, $this->files);
            $feedbackFileDecorator->decorate();
        }

        if ($postPublish == true) {
            // received signal to publish this
            if (!$feedback->isPublished()) {
                $feedback->setPublishedAt(\Alaress_Date_Format::now());
            }
        }

        /* Attach feedback to a particular Response instance
         */
        if ($this->response) {
            $feedback->setResponse($this->response);
        }

        return $feedback;
    }
}
