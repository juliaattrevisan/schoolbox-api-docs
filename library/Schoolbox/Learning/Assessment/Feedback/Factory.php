<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Feedback;

use \Schoolbox\User;
use \Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\IResponse;
use \Schoolbox\Learning\Assessment\IFeedback;
use \Schoolbox\Learning\Assessment\Participant;
use \Schoolbox\Learning\MarkScheme\IMarkScheme;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;

class Factory
{

    /**
     * @var IFeedbackDataSource
     */
    private $feedbackDataSource;

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
     * @var array
     */
    private $feedbacks;

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
     * @var IResponse
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
     * @param IFeedbackDataSource $feedbackDataSource
     * @param Participant $participant
     * @param User $currentUser
     * @param Collection $feedbacks
     * @param $mark
     * @param $comment
     * @param $publish
     * @param $files
     * @param IResponse|null $response
     */
    public function __construct(
        IFeedbackDataSource $feedbackDataSource,
        Participant $participant, User $currentUser, Collection $feedbacks,
        $mark, $comment, $publish, $files,
        IResponse $response = null
    ) {
        $this->setFeedbackDataSource($feedbackDataSource);
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
     * Produce a Feedback with the assorted details in it
     * such as mark, comment, publish, authors and attachments
     *
     *
     * @return IFeedback
     */
    public function produce()
    {
        if ($this->getFeedbacks()->count() == 0 || $this->getFeedbacks()->first()->isPublished()) {

            // if none, or published: create new draft
            /** @var DueWorkFeedback $feedback */
            $feedback = $this->getFeedbackDataSource()->createModel($this->getParticipant()->getAssessable());

            $feedback
                ->setReviewee($this->getParticipant()->getUser())
                ->setCreatedAt(\Alaress_Date_Format::now())
            ;

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
         * If content is all empty and they are trying to create a draft or publish then fail out.
         * Otherwise it means they are updating an existing draft; if there's no content: delete the draft.
         */
        if (!$hasContent && ($postPublish == true || $this->getResponseCode() == 201)) {
            $old_one = $this->getResponseCode();
            $this->setResponseCode(400);
            //throw new \InvalidArgumentException('One of mark, comment and files should not be empty.');
            throw new \InvalidArgumentException($old_one);
        } elseif (!$hasContent) {
            $feedback->setDeletedAt(\Alaress_Date_Format::now());
            $feedback->setResponse($this->getResponse());
            return $feedback;
        }

        /** @var IFeedback $feedback */
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
     * @return mixed
     */
    private function getFeedbackDataSource()
    {
        return $this->feedbackDataSource;
    }

    /**
     * @param mixed $feedbackDataSource
     * @return $this
     */
    private function setFeedbackDataSource($feedbackDataSource)
    {
        $this->feedbackDataSource = $feedbackDataSource;
        return $this;
    }

    /**
     * @return mixed
     */
    private function getParticipant()
    {
        return $this->participant;
    }

    /**
     * @param mixed $participant
     * @return $this
     */
    private function setParticipant($participant)
    {
        $this->participant = $participant;
        return $this;
    }

    /**
     * @return mixed
     */
    private function getCurrentUser()
    {
        return $this->currentUser;
    }

    /**
     * @param mixed $currentUser
     * @return $this
     */
    private function setCurrentUser($currentUser)
    {
        $this->currentUser = $currentUser;
        return $this;
    }

    /**
     * @return mixed
     */
    private function getFeedbacks()
    {
        return $this->feedbacks;
    }

    /**
     * @param mixed $feedbacks
     * @return $this
     */
    private function setFeedbacks($feedbacks)
    {
        $this->feedbacks = $feedbacks;
        return $this;
    }

    /**
     * @return mixed
     */
    private function getMark()
    {
        return $this->mark;
    }

    /**
     * @param mixed $mark
     * @return $this
     */
    private function setMark($mark)
    {
        $this->mark = $mark;
        return $this;
    }

    /**
     * @return mixed
     */
    private function getComment()
    {
        return $this->comment;
    }

    /**
     * @param mixed $comment
     * @return $this
     */
    private function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return mixed
     */
    private function getPublish()
    {
        return $this->publish;
    }

    /**
     * @param mixed $publish
     * @return $this
     */
    private function setPublish($publish)
    {
        $this->publish = $publish;
        return $this;
    }

    /**
     * @return mixed
     */
    private function getFiles()
    {
        return $this->files;
    }

    /**
     * @param mixed $files
     * @return $this
     */
    private function setFiles($files)
    {
        $this->files = $files;
        return $this;
    }

    /**
     * @return mixed
     */
    private function getResponse()
    {
        return $this->response;
    }

    /**
     * @param mixed $response
     * @return $this
     */
    private function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * @param mixed $responseCode
     * @return $this
     */
    private function setResponseCode($responseCode)
    {
        $this->responseCode = $responseCode;
        return $this;
    }
}