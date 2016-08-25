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

use \Schoolbox\User;

interface IFeedback
{
    /**
     * Calculate the final mark for this IAssessable
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
     * @return float
     */
    public function getHumanReadableMark();

    /**
     * @param float $humanReadableMark
     * @return $this
     */
    public function setHumanReadableMark($humanReadableMark);

    /**
     * @return mixed
     */
    public function getNormalisedMark();

    /**
     * @param mixed $normalisedMark
     * @return $this
     */
    public function setNormalisedMark($normalisedMark);

    /**
     * @return string
     */
    public function getComment();

    /**
     * @param string $comment
     * @return $this
     */
    public function setComment($comment);

    /**
     * @return User
     */
    public function getReviewer();

    /**
     * @param User $reviewer
     * @return $this
     */
    public function setReviewer($reviewer);

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
     * @return \Alaress_Date_Format
     */
    public function getPublishedAt();

    /**
     * @param \Alaress_Date_Format $publishedAt
     * @return $this
     */
    public function setPublishedAt($publishedAt);

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