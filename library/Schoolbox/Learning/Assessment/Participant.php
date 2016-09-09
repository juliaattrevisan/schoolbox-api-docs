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
        $outcome = $this->assessableFeedbackTraversal($assessable, self::TRAVERSAL_MARKED);

        return $outcome !== null;
    }

    /**
     * This Participant received a Published Feedback for the $assessable
     *
     * @param IAssessable $assessable
     * @return bool
     */
    public function isAssessablePublished(IAssessable $assessable)
    {
        $outcome = $this->assessableFeedbackTraversal($assessable, self::TRAVERSAL_PUBLISHED);

        return $outcome !== null;
    }

    /**
     * Find the most recent published IFeedback for an IAssessable for this Participant
     *
     * @param IAssessable $assessable
     * @return null|IFeedback
     */
    public function findMostRecentPublishedFeedback(IAssessable $assessable)
    {
        return $this->assessableFeedbackTraversal($assessable, self::TRAVERSAL_PUBLISHED);
    }

    /**
     * Find the most recent IFeedback for an IAssessable for this Participant
     *
     * @param IAssessable $assessable
     * @return null|IFeedback
     */
    public function findMostRecentFeedback(IAssessable $assessable)
    {
        return $this->assessableFeedbackTraversal($assessable, self::TRAVERSAL_MARKED);
    }

    /**
     * Finds the latest Feedback which determined whether an IAssessable was
     * marked or published based on the $type arg.
     *
     * A unified traversal of Feedbacks for the $assessable for this Participant
     *
     * This should probably be rammed away into its own class, as there will be
     * a lot of traversals coming up IMHO
     *
     * @param IAssessable $assessable
     * @param $type
     * @return IFeedback|null
     */
    private function assessableFeedbackTraversal(IAssessable $assessable, $type)
    {
        $outcome = null;

        $numResponses = $this->findAllResponsesForAssessable($assessable)->count();
        $numFeedbacks = $this->findAllFeedbackForAssessable($assessable)->count();

        /* No Responses, No Feedback >>> not marked
         */
        if (!$numResponses && !$numFeedbacks) {
            $outcome = null;
        }
        /* At least one Response, No Feedback >>> not marked
         */
        elseif ($numResponses && !$numFeedbacks) {
            $outcome = null;
        }
        /* No Responses, at least one Feedback >>> marked
         */
        elseif (!$numResponses && $numFeedbacks) {

            /** @var IFeedback $latestFeedback */
            $allFeedbacks = $this->findAllFeedbackForAssessable($assessable);
            $latestFeedback = $allFeedbacks->first();

            /* Compare to see whether $latestFeedback matches $type requirement
             */
            if ($type == self::TRAVERSAL_PUBLISHED) {
                foreach ($allFeedbacks as $currentFeedback) {
                    if ($currentFeedback->isPublished() && $currentFeedback->getPublishedAt()->gt($latestFeedback->getUpdatedAt())) {
                        $latestFeedback = $currentFeedback;
                    }
                }

                if ($latestFeedback->isPublished()) {
                    return $latestFeedback;
                }

                return null;
            }
            else if ($type == self::TRAVERSAL_MARKED) {
                foreach ($allFeedbacks as $currentFeedback) {
                    if ($currentFeedback->getUpdatedAt()->gt($latestFeedback->getUpdatedAt())) {
                        $latestFeedback = $currentFeedback;
                    }
                }
            }

            return null;
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

            /* Find the most recent feedback
             * Compare to see which one is the most recent action to occur
             */
            if ($type == self::TRAVERSAL_PUBLISHED) {
                foreach ($allFeedbacks as $currentFeedback) {
                    if ($currentFeedback->isPublished()) {

                        /* Look to the future!
                         * Make sure this feedback is more recent than the previously-known latest feedback
                         * Make sure this feedback is more recent than the response
                         */
                        if ($currentFeedback->getPublishedAt()->gt($latestFeedback->getPublishedAt()) && $currentFeedback->getPublishedAt()->gt($latestResponse->getUpdatedAt())) {
                            $latestFeedback = $currentFeedback;
                        }
                    }
                }

                /* Check in case the first one is the most recent anyway
                 * and that it is also actually published
                 */
                if ($latestFeedback->isPublished()) {
                    return $latestFeedback;
                }

                return null;
            }
            else if ($type == self::TRAVERSAL_MARKED) {
                foreach ($allFeedbacks as $currentFeedback) {
                    if ($currentFeedback->getUpdatedAt()->gt($latestFeedback->getUpdatedAt())) {
                        $latestFeedback = $currentFeedback;
                    }
                }

                if ($latestFeedback->getUpdatedAt()->gt($latestResponse->getUpdatedAt())) {
                    return $latestFeedback;
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