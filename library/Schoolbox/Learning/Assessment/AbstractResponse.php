<?php
/**
 * AbstractResponse
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\Collection;

class AbstractResponse implements IResponse
{
    /**
     * @var Collection
     */
    protected $allFeedback;

    /**
     * @var \Alaress_Date_Format
     */
    protected $createdAt;

    /**
     * @var \Alaress_Date_Format
     */
    protected $updatedAt;

    /**
     * @var \Alaress_Date_Format
     */
    protected $deletedAt;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->setAllFeedback(new Collection());
    }

    /**
     * Find the most recent Feedback with a non-null Mark value,
     * based on its Updated field
     */
    public function findMostRecentNonNullFeedback()
    {
        if ($this->getAllFeedback()->count()) {
            $latest = $this->getAllFeedback()->first();

            foreach ($this->getAllFeedback() as $currentFeedback) {
                if ($currentFeedback->getUpdatedAt()->gt($latest->getUpdatedAt()) && !$currentFeedback->isNullMark()) {
                    $latest = $currentFeedback;
                }
            }

            /* If the ->first() instance is actually null
             * and all the other Feedbacks are also null
             * return null
             *
             * Otherwise return the Feedback itself
             */
            if (!$latest->isNullMark()) {
                return $latest;
            }
        }

        return null;
    }

    /**
     * Find the most recent Feedback, based on its Updated field
     */
    public function findMostRecentFeedback()
    {
        if ($this->getAllFeedback()->count()) {
            $latest = $this->getAllFeedback()->first();

            foreach ($this->getAllFeedback() as $currentFeedback) {
                if ($currentFeedback->getUpdatedAt()->gt($latest->getUpdatedAt())) {
                    $latest = $currentFeedback;
                }
            }

            return $latest;
        }

        return null;
    }

    /* Getters/Setters
     */

    /**
     * @return Collection
     */
    public function getAllFeedback()
    {
        return $this->allFeedback;
    }

    /**
     * @param Collection $allFeedback
     * @return $this
     */
    public function setAllFeedback($allFeedback)
    {
        $this->allFeedback = $allFeedback;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \Alaress_Date_Format $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param \Alaress_Date_Format $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @param \Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }
}