<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Task\Completion;

use \Schoolbox\Learning\Assessment\AbstractResponse;
use Schoolbox\Learning\Assessment\Task\CompletionStatusTrait;

class Model extends AbstractResponse
{
    use CompletionStatusTrait;

    /**
     * Completions are submitted at the updatedAt value
     * in case the Completion is modified by the Student
     *
     * @return \Alaress_Date_Format
     */
    public function getSubmittedAt()
    {
        return $this->getUpdatedAt();
    }

    /* Tasks always return Null marks for Feedbacks
     */

    /**
     * Find the most recent Feedback with a non-null Mark value,
     * based on its Updated field
     *
     * Assumes getAllFeedback() is sorted in reverse chronological order
     */
    public function findMostRecentFeedback()
    {
        if ($this->getAllFeedback()->count()) {
            return $this->getAllFeedback()->first();
        }

        return null;
    }

    /**
     * Find the most recent Published Feedback with a non-null Mark value,
     * based on its Updated field
     *
     * Assumes getAllFeedback() is sorted in reverse chronological order
     */
    public function findMostRecentPublishedFeedback()
    {
        if ($this->getAllFeedback()->count()) {
            $latest = $this->getAllFeedback()->first();

            foreach ($this->getAllFeedback() as $currentFeedback) {
                if ($currentFeedback->getPublishedAt()) {
                    if ($currentFeedback->getPublishedAt()->gt($latest->getPublishedAt())) {
                        $latest = $currentFeedback;
                    }
                }
            }

            return $latest;
        }

        return null;
    }
}