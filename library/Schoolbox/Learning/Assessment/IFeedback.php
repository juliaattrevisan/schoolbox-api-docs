<?php
/**
 * IReview
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;


interface IFeedback
{

    /**
     * Calculate the final mark for the Review instance
     *
     * @return mixed
     */
    public function calculateMark();

    /**
     * This IAssessable has a mark value of null
     *
     * @return bool
     */
    public function isNullMark();

    /**
     * Determine whether this Feedback has been published.
     * If the publish date is null, it's a draft
     *
     * @return bool
     */
    public function isPublished();

    /* Getters/Setters
     */
    public function getId();
    public function setId($id);
    public function getHumanReadableMark();
    public function setHumanReadableMark($humanReadableMark);
    public function getNormalisedMark();
    public function setNormalisedMark($normalisedMark);
    public function getComment();
    public function setComment($comment);
    public function getReviewer();
    public function setReviewer($reviewer);
    public function getPublishedAt();
    public function setPublishedAt($publishedAt);
    public function getCreatedAt();
    public function setCreatedAt($createdAt);
    public function getUpdatedAt();
    public function setUpdatedAt($updatedAt);
    public function getDeletedAt();
    public function setDeletedAt($deletedAt);
}