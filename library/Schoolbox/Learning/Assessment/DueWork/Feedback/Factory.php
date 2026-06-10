<?php

/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\Feedback\FileDecorator;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\Feedback\SubSource\DueWork as DueWorkFeedbackDataSource;
use Schoolbox\Learning\DueWork\StateMapper;

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
     * @var string|null
     */
    private $mark;

    /**
     * @var bool
     */
    private $resubmissionRequired;

    /**
     * @var string
     */
    private $comment;

    /**
     * @var bool
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
     * @var DueWorkResponse|null
     */
    private $response;


    /**
     * Factory constructor.
     *
     * @param IFeedbackDataSource|DueWorkFeedbackDataSource $dueWorkFeedbackDataSource
     * @param Participant                                   $participant
     * @param User                                          $currentUser
     * @param string                                        $mark
     * @param bool                                          $resubmissionRequired
     * @param string                                        $comment
     * @param string|null                                   $publish   one of [null|'0'|'1']
     * @param array|null                                    $files
     * @param bool                                          $fromInlineGrader
     * @param string                                        $reviewRoute
     * @param IFeedback|DueWorkFeedback|null                $mostRecentFeedback
     * @param DueWorkResponse|null                          $response
     */
    public function __construct(
        IFeedbackDataSource $dueWorkFeedbackDataSource,
        Participant $participant,
        User $currentUser,
        $mark,
        bool $resubmissionRequired,
        $comment,
        $publish,
        $files,
        $fromInlineGrader,
        string $reviewRoute,
        ?IFeedback $mostRecentFeedback = null,
        ?DueWorkResponse $response = null
    ) {
        $this->dueWorkFeedbackDataSource = $dueWorkFeedbackDataSource;
        $this->participant               = $participant;
        $this->currentUser               = $currentUser;
        $this->mostRecentFeedback        = $mostRecentFeedback;
        $this->mark                      = ($mark === null || $mark === '') ? null : (trim($mark) === '' ? $mark : trim($mark));
        $this->resubmissionRequired      = $resubmissionRequired;
        $this->comment                   = $comment ? trim($comment) : '';
        $this->publish                   = $publish === '1';
        $this->files                     = $files;
        $this->fromInlineGrader          = $fromInlineGrader;
        $this->reviewRoute               = $reviewRoute;
        $this->response                  = $response;
    }


    /**
     * Produce a DueWork Feedback with the assorted details in it
     * such as mark, comment, publish, authors and attachments
     *
     * @return DueWorkFeedback
     * @throws Exception
     * @throws Deleted
     * @throws \UnexpectedValueException
     */
    public function produce(): DueWorkFeedback
    {
        // Is this the first feedback for this participant, or is most recent feedback already published ?
        if (is_null($this->mostRecentFeedback) || $this->mostRecentFeedback->isPublished()) {
            // create new feedback
            /** @var DueWorkFeedback $feedback */
            $feedback = $this->dueWorkFeedbackDataSource->createModel($this->participant->getAssessable())
                ->setReviewee($this->participant->getUser())
                ->setCreatedAt(\Alaress_Date_Format::now())
                /* $reviewer is the creator of the feedback
                 * $publishedBy is the publisher of the feedback
                 * We'll never know who might have altered the feedback in between
                 */
                ->setReviewer($this->currentUser);

            $this->participant->getAllFeedbacks()->add($feedback);

            // copy attachments over from the previous feedback.
            if ($this->mostRecentFeedback && $this->mostRecentFeedback->getAllFiles()->count() > 0) {
                $filesToCopy = new Collection();
                foreach ($this->mostRecentFeedback->getAllFiles() as $attachment) {
                    // DO NOT COPY ACROSS worksheet feedbacks
                    if (!$attachment->getIsWorksheet()) {
                        $filesToCopy->add($attachment);
                    }
                }
                $feedback->setAllFiles($filesToCopy);
            }
        } else {
            // most recent is draft, use that
            $feedback = $this->mostRecentFeedback;
        }

        // mutate model found, and save
        /** @var DueWorkFeedback $feedback */
        $feedback
            ->setReviewRoute($this->reviewRoute)
            ->setUpdatedAt(\Alaress_Date_Format::now())
            ->setComment($this->comment)
            /* Remember who last touched it,
             * presuming they're about to publish it
             */
            ->setPublishedBy($this->currentUser->getId())
            ->setResubmissionRequired($this->resubmissionRequired);

        if ($this->mark !== null && $this->mark !== '') {
            // mark defined and non-empty
            try {
                /** @var \Schoolbox\Learning\Assessment\DueWork\Model $assessable */
                $assessable = $this->participant->getAssessable();

                /** Get mark schemes
                 * - Instructor mark scheme is used to normalise what was inputted by the user
                 * - Learner mark scheme is used when displaying the mark back to learners/parents
                 */
                $instructorScheme = $assessable->getInstructorMarkScheme();
                $learnerScheme = $assessable->getMarkScheme();

                // verify normalisation
                // for self-assessment, we use the learner mark scheme instead
                $normScheme = $feedback->isSelfAssessment() ? $learnerScheme : $instructorScheme;
                $norm = $normScheme->getNormalisedMark($this->mark);

                /* Always rederive/sanitise both the learner and instructor human readable marks, even if they're the same.
                 * For example, this ensures a value of "5" using a percentage mark type becomes "5 %"
                 */
                $humanReadableMark = $learnerScheme->getHumanReadableMark($norm);
                $humanReadableInstructorMark = $instructorScheme->getHumanReadableMark($norm);

                $feedback
                    ->setNormalisedMark($norm)
                    ->setHumanReadableMark($humanReadableMark)
                    ->setHumanReadableInstructorMark($humanReadableInstructorMark);
            } catch (\InvalidArgumentException $e) {
                $code = StateMapper::getInstance()->findOneByColumn('display_as', $this->mark);

                $feedback
                    ->setNormalisedMark($code->getNormaliseAs())
                    ->setHumanReadableMark($this->mark)
                    ->setHumanReadableInstructorMark($this->mark);
            }
        } else {
            /* "None" mark type was used here, so we won't get a teacher-assigned mark in this usage
             * Set norm and hr marks to be acceptable defaults.
             */
            $feedback
                ->setNormalisedMark(null)
                ->setHumanReadableMark('')
                ->setHumanReadableInstructorMark('');
        }

        // Attach any attached files (if this kind of attachment accepts them)
        if ($this->fromInlineGrader) {
            $feedbackFileDecorator = new FileDecorator($feedback, $this->files);
            $feedbackFileDecorator->decorate();
        }

        if ($this->publish && !$feedback->isPublished()) {
            // received signal to publish this
            $feedback->setPublishedAt(\Alaress_Date_Format::now());
        }

        /* Attach feedback to a particular Response instance, but only if the
         * response has not been deleted
         */
        if ($this->response && !$this->response->isDeleted()) {
            $feedback->setResponse($this->response);
        }

        return $feedback;
    }
}
