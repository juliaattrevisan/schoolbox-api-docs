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
    public function findAllResponses(IAssessable $assessable)
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
    public function findAllFeedback(IAssessable $assessable)
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