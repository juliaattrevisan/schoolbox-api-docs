<?php
/**
 * IResponse
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\Collection;
use \Schoolbox\User;

interface IResponse
{

    /**
     * Find the most recent Feedback with a non-null Mark value,
     * based on its Updated field
     *
     * Assumes getAllFeedback() is sorted in reverse chronological order
     */
    public function findMostRecentFeedback();

    /**
     * Find the most recent Published Feedback with a non-null Mark value,
     * based on its Updated field
     *
     * Assumes getAllFeedback() is sorted in reverse chronological order
     */
    public function findMostRecentPublishedFeedback();

    /* Getters/Setters
     */

    /**
     * @var string
     */
    public function getId();

    /**
     * @param  int $id
     *
     * @return $this
     */
    public function setId($id);

    /**
     * @return Collection
     */
    public function getAllFeedback();

    /**
     * @param Collection $allFeedback
     * @return $this
     */
    public function setAllFeedback($allFeedback);

    /**
     * @return User
     */
    public function getSubmittedBy();

    /**
     * @param User $submittedBy
     * @return $this
     */
    public function setSubmittedBy($submittedBy);

    /**
     * @return User
     */
    public function getSubmittedOnBehalfOf();

    /**
     * @param User $submittedOnBehalfOf
     * @return $this
     */
    public function setSubmittedOnBehalfOf($submittedOnBehalfOf);

    /**
     * @return IAssessable
     */
    public function getAssessable();

    /**
     * @param IAssessable $assessable
     * @return $this
     */
    public function setAssessable($assessable);

    /**
     * Created date
     *
     * @var \Alaress_Date_Format
     */
    public function getCreatedAt();

    /**
     * @param  \Alaress_Date_Format $createdAt
     *
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt();

    /**
     * @param  \Alaress_Date_Format $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt($updatedAt);

    /**
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt();

    /**
     * @param  \Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt($deletedAt);
}