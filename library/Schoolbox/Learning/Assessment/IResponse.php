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


interface IResponse
{

    /**
     * Find the most recent Feedback with a non-null Mark value,
     * based on its Updated field
     */
    public function findMostRecentNonNullFeedback();

    /**
     * Find the most recent Feedback, based on its Updated field
     */
    public function findMostRecentFeedback();

    /* Getters/Setters
     */
    public function getAllFeedback();
    public function setAllFeedback($allFeedback);
    public function getSubmittedBy();
    public function setSubmittedBy($submittedBy);
    public function getCreatedAt();
    public function setCreatedAt($createdAt);
    public function getUpdatedAt();
    public function setUpdatedAt($updatedAt);
    public function getDeletedAt();
    public function setDeletedAt($deletedAt);
}