<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Schoolbox\Learning\Assessment\Feedback\FileDecorator;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use \Schoolbox\User;
use \Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\IResponse;
use \Schoolbox\Learning\Assessment\Participant;
use \Schoolbox\Learning\MarkScheme\IMarkScheme;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkResponse;
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
     * All the feedbacks for this participation
     *
     * @var Collection|DueWorkFeedback[]
     */
    private $dueWorkFeedbacks;

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
     * @var DueWorkResponse
     */
    private $response;

    /**
     * HTTP code representing the outcome of this $feedback production
     *
     * @var int
     */
    private $responseCode;

    /**
     * Factory constructor.
     *
     * @param DueWorkFeedbackDataSource $dueWorkFeedbackDataSource
     * @param Participant               $participant
     * @param User                      $currentUser
     * @param Collection                $feedbacks
     * @param $mark
     * @param $comment
     * @param $publish
     * @param $files
     * @param DueWorkResponse|null      $response
     */
    public function __construct(
        DueWorkFeedbackDataSource $dueWorkFeedbackDataSource,
        Participant $participant, User $currentUser, Collection $feedbacks,
        $mark, $comment, $publish, $files,
        DueWorkResponse $response = null
    ) {
        $this->setFeedbackDataSource($dueWorkFeedbackDataSource);
        $this->setParticipant($participant);
        $this->setCurrentUser($currentUser);
        $this->setFeedbacks($feedbacks);
        $this->setMark($mark);
        $this->setComment($comment);
        $this->setPublish($publish);
        $this->setFiles($files);
        $this->setResponse($response);
    }

    /**
     * Produce a DueWork Feedback with the assorted details in it
     * such as mark, comment, publish, authors and attachments
     *
     * @return DueWorkFeedback
     */
    public function produce()
    {
        if ($this->getFeedbacks()->count() == 0 || $this->getFeedbacks()->first()->isPublished()) {

            // if none, or published: create new draft
            /** @var DueWorkFeedback $feedback */
            $feedback = $this->getDueWorkFeedbackDataSource()->createModel($this->getParticipant()->getAssessable());

            $feedback
                ->setReviewee($this->getParticipant()->getUser())
                ->setCreatedAt(\Alaress_Date_Format::now());

            // on CREATE, return 201 Created
            $this->setResponseCode(201);

        } else {
            // most recent is draft, use that
            $feedback = $this->getFeedbacks()->first();

            // on UPDATE, return 204 No Content
            $this->setResponseCode(204);
        }

        // mutate model found, and save
        $postMark    = trim($this->getMark());
        $postComment = trim($this->getComment());
        $postPublish = trim($this->getPublish());

        /**
         * Check if there's any content to this feedback
         */
        $hasContent = false;
        if (strlen($postMark) || strlen($postComment) || !empty($this->getFiles())) {
            $hasContent = true;
        }

        /**
         * If they are updating an existing draft and there's no content: delete the draft.
         * I have purposefully allowed empty drafts when creating a new draft, otherwise it's just
         * too confusing for users when going from a published feedback.
         * I've also purposefully allowed publishing of empty feedback, in order for teachers to overwrite a mistake
         * (teachers are not allowed to delete from the feedback history).
         */
        if (!$hasContent && !$postPublish && $this->getResponseCode() == 204) {
            $feedback->setDeletedAt(\Alaress_Date_Format::now());
            $feedback->setResponse($this->getResponse());
            return $feedback;
        }

        /** @var DueWorkFeedback $feedback */
        $feedback
            ->setReviewer($this->getCurrentUser())
            ->setUpdatedAt(\Alaress_Date_Format::now())
            ->setComment($postComment);

        if ($postMark !== '') {

            // mark defined and non-empty
            try {
                /** @var IMarkScheme $scheme */
                $scheme = $this->getParticipant()->getAssessable()->getMarkScheme();

                // verify normalisation, then sanitise the output
                $norm = $scheme->getNormalisedMark($postMark);
                $sane = $scheme->getHumanReadableMark($norm);

                $feedback
                    ->setNormalisedMark($norm)
                    ->setHumanReadableMark($sane);
            }
            catch (\InvalidArgumentException $e) {
                $code = \DueWork_ErrorMapper::getInstance()->findOneByColumn('display_as', $postMark);

                $feedback
                    ->setNormalisedMark($code->getNormaliseAs())
                    ->setHumanReadableMark($postMark);
            }
        }
        else {
            //If mark is set to empty string this is a special case
            //TODO potentially the mark post var may not have been set, we may lose the mark in this case so we should
            //test the existence of the post var as well

            $feedback
                ->setNormalisedMark(null)
                ->setHumanReadableMark('');
        }

        // Attach any attached files (if this kind of attachment accepts them)
        $feedbackFileDecorator = new FileDecorator($feedback, $this->getFiles());
        $feedbackFileDecorator->decorate();

        if ($postPublish == true) {
            // received signal to publish this
            if (!$feedback->isPublished()) {
                $feedback->setPublishedAt(\Alaress_Date_Format::now());
            }
        }

        /* Attach feedback to a particular Response instance
         */
        if ($this->getResponse()) {
            $feedback->setResponse($this->getResponse());
        }

        return $feedback;
    }

    /* Getters/Setters
     */

    /**
     * @return IFeedbackDataSource
     */
    private function getDueWorkFeedbackDataSource()
    {
        return $this->dueWorkFeedbackDataSource;
    }

    /**
     * @param IFeedbackDataSource $feedbackDataSource
     * @return $this
     */
    private function setFeedbackDataSource($feedbackDataSource)
    {
        $this->dueWorkFeedbackDataSource = $feedbackDataSource;
        return $this;
    }

    /**
     * @return Participant
     */
    private function getParticipant()
    {
        return $this->participant;
    }

    /**
     * @param Participant $participant
     * @return $this
     */
    private function setParticipant($participant)
    {
        $this->participant = $participant;
        return $this;
    }

    /**
     * @return User
     */
    private function getCurrentUser()
    {
        return $this->currentUser;
    }

    /**
     * @param User $currentUser
     * @return $this
     */
    private function setCurrentUser($currentUser)
    {
        $this->currentUser = $currentUser;
        return $this;
    }

    /**
     * @return Collection|DueWorkFeedback[]
     */
    private function getFeedbacks()
    {
        return $this->dueWorkFeedbacks;
    }

    /**
     * @param Collection|DueWorkFeedback[] $feedbacks
     * @return $this
     */
    private function setFeedbacks(Collection $feedbacks)
    {
        $this->dueWorkFeedbacks = $feedbacks;
        return $this;
    }

    /**
     * @return string
     */
    private function getMark()
    {
        return $this->mark;
    }

    /**
     * @param string $mark
     * @return $this
     */
    private function setMark($mark)
    {
        $this->mark = $mark;
        return $this;
    }

    /**
     * @return string
     */
    private function getComment()
    {
        return $this->comment;
    }

    /**
     * @param string $comment
     * @return $this
     */
    private function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return string
     */
    private function getPublish()
    {
        return $this->publish;
    }

    /**
     * @param string $publish
     * @return $this
     */
    private function setPublish($publish)
    {
        $this->publish = $publish;
        return $this;
    }

    /**
     * @return array
     */
    private function getFiles()
    {
        return $this->files;
    }

    /**
     * @param array $files
     * @return $this
     */
    private function setFiles($files)
    {
        $this->files = $files;
        return $this;
    }

    /**
     * @return IResponse
     */
    private function getResponse()
    {
        return $this->response;
    }

    /**
     * @param IResponse $response
     * @return $this
     */
    private function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return int
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * @param int $responseCode
     * @return $this
     */
    private function setResponseCode($responseCode)
    {
        $this->responseCode = $responseCode;
        return $this;
    }
}
