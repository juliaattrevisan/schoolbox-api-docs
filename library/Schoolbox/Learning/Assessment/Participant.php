<?php
/**
 * Participant
 *
 * A "flattened" version of the Submission/Completion/Attempt--Feedback
 * hierarchy that currently exists, so you don't need to traverse so much
 * to find particular Feedbacks for a particular User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\User;
use \Schoolbox\Collection;

class Participant
{
    const TRAVERSAL_MARKED = 1;
    const TRAVERSAL_PUBLISHED = 2;

    /**
     * The User to whom all these Responses and Feedbacks are relevant
     *
     * @var User
     */
    protected $user;

    /**
     * A Collection of IResponse instances that Students spawn
     *
     * @var Collection
     */
    protected $allResponses;

    /**
     * A Collection of IFeedback instances that Teachers spawn
     *
     * @var Collection
     */
    protected $allFeedbacks;

    /**
     * Participant constructor.
     *
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->setUser($user);
        $this->setAllResponses(new Collection());
        $this->setAllFeedbacks(new Collection());
    }

    /**
     * Find all the Responses that were spawned for a particular IAssessable
     *
     * @param IAssessable $assessable
     * @return Collection
     */
    public function findAllResponsesForAssessable(IAssessable $assessable)
    {
        $assessableClass = get_class($assessable);

        $collection = new Collection();

        /** @var IResponse $currentResponse */
        foreach ($this->getAllResponses() as $currentResponse) {

            /** @var IAssessable $currentAssessable */
            $currentAssessable = $currentResponse->getAssessable();

            /* So that $dueWork 1 doesn't get matched with $quiz 1
             * or $task 1 - have to check types as well as id
             */
            if ($currentAssessable instanceof $assessableClass) {
                if ($currentAssessable->getId() == $assessable->getId()) {
                    $collection->add($currentResponse);
                }
            }
        }

        return $collection;
    }

    /**
     * Find all the Feedbacks that were spawned for a particular IAssessable
     *
     * @param IAssessable $assessable
     * @return Collection
     */
    public function findAllFeedbackForAssessable(IAssessable $assessable)
    {
        $assessableClass = get_class($assessable);

        $collection = new Collection();

        /** @var IFeedback $currentFeedback */
        foreach ($this->getAllFeedbacks() as $currentFeedback) {

            /** @var IAssessable $currentAssessable */
            $currentAssessable = $currentFeedback->getAssessable();

            /* So that $dueWork 1 doesn't get matched with $quiz 1
             * or $task 1 - have to check types as well as id
             */
            if ($currentAssessable instanceof $assessableClass) {
                if ($currentAssessable->getId() == $assessable->getId()) {
                    $collection->add($currentFeedback);
                }
            }
        }

        return $collection;
    }

    /* Traveller
     */

    /**
     * This Participant received a Feedback for the $assessable
     *
     * @param IAssessable $assessable
     * @return bool
     */
    public function isAssessableMarked(IAssessable $assessable)
    {
        return $this->assessableFeedbackTraversal($assessable, self::TRAVERSAL_MARKED);
    }

    /**
     * This Participant received a Published Feedback for the $assessable
     *
     * @param IAssessable $assessable
     * @return bool
     */
    public function isAssessablePublished(IAssessable $assessable)
    {
        return $this->assessableFeedbackTraversal($assessable, self::TRAVERSAL_PUBLISHED);
    }

    /**
     * A unified traversal of Feedbacks for the $assessable for this Participant
     *
     * This should probably be rammed away into its own class, as there will be
     * a lot of traversals coming up IMHO
     *
     * @param IAssessable $assessable
     * @param $type
     * @return bool
     */
    private function assessableFeedbackTraversal(IAssessable $assessable, $type)
    {
        $outcome = false;

        $numResponses = $this->findAllResponsesForAssessable($assessable)->count();
        $numFeedbacks = $this->findAllFeedbackForAssessable($assessable)->count();

        /* No Responses, No Feedback >>> not marked
         */
        if (!$numResponses && !$numFeedbacks) {
            $outcome = false;
        }
        /* At least one Response, No Feedback >>> not marked
         */
        elseif ($numResponses && !$numFeedbacks) {
            $outcome = false;
        }
        /* No Responses, at least one Feedback >>> marked
         */
        elseif (!$numResponses && $numFeedbacks) {
            $outcome = true;
        }
        /* Time to inspect the stacks
         */
        else {

            /** @var IResponse $latestResponse */
            $allResponses = $this->findAllResponsesForAssessable($assessable);
            $latestResponse = $allResponses->first();

            /** @var IFeedback $latestFeedback */
            $allFeedbacks = $this->findAllFeedbackForAssessable($assessable);
            $latestFeedback = $allFeedbacks->first();

            /* Find the most recent Response
             *
             * @var IResponse $currentResponse
             */
            foreach ($allResponses as $currentResponse) {
                if ($currentResponse->getUpdatedAt()->gt($latestResponse->getUpdatedAt())) {
                    $latestResponse = $currentResponse;
                }
            }

            /* Find the most recent Feedback
             *
             * @var IFeedback $currentFeedback
             */
            foreach ($allFeedbacks as $currentFeedback) {
                if ($currentFeedback->getUpdatedAt()->gt($latestFeedback->getUpdatedAt())) {
                    $latestFeedback = $currentFeedback;
                }
            }

            /* Compare to see which one is the most recent action to occur
             */
            if ($type == self::TRAVERSAL_PUBLISHED) {
                if ($latestFeedback->getUpdatedAt()->gt($latestResponse->getUpdatedAt()) && $latestFeedback->isPublished()) {
                    $outcome = true;
                }
            }
            else if ($type == self::TRAVERSAL_MARKED) {
                if ($latestFeedback->getUpdatedAt()->gt($latestResponse->getUpdatedAt())) {
                    $outcome = true;
                }
            }
        }

        return $outcome;
    }

    /* Getters/Setters
     */

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return Collection
     */
    public function getAllResponses()
    {
        return $this->allResponses;
    }

    /**
     * @param Collection $allResponses
     */
    public function setAllResponses($allResponses)
    {
        $this->allResponses = $allResponses;
    }

    /**
     * @return Collection
     */
    public function getAllFeedbacks()
    {
        return $this->allFeedbacks;
    }

    /**
     * @param Collection $allFeedbacks
     */
    public function setAllFeedbacks($allFeedbacks)
    {
        $this->allFeedbacks = $allFeedbacks;
    }
}