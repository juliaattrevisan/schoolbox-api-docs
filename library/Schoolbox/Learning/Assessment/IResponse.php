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

use Alaress_Date_Format;
use \Schoolbox\Collection;
use \Schoolbox\User;

interface IResponse
{

    /**
     * Find the most recent Feedback with a non-null Mark value,
     * based on its 'publishedAt' property
     *
     * @return IFeedback|null
     */
    public function findMostRecentInstructorGivenFeedback(): ?IFeedback;

    /**
     * Find the most recent Published Feedback with a non-null Mark value,
     * based on its 'publishedAt' property
     *
     * @return IFeedback|null
     */
    public function findMostRecentInstructorGivenPublishedFeedback(): ?IFeedback;

    /**
     * Find the most recent self-assessed Feedback with a non-null Mark value,
     * based on its 'publishedAt' property
     *
     * @return IFeedback|null
     */
    public function findMostRecentSelfAssessedFeedback(): ?IFeedback;

    /**
     * Find the most recent self-assessed Published Feedback with a non-null Mark value,
     * based on its 'publishedAt' property
     *
     * @return IFeedback|null
     */
    public function findMostRecentSelfAssessedPublishedFeedback(): ?IFeedback;


    /**
     * Does this IResponse have published feedback?
     *
     * @deprecated Ask the question whether this has instructor-given or self-assessed feedback instead
     * @return bool
     */
    public function hasPublishedFeedback();

    /* Getters/Setters
     */

    /**
     * @var int
     */
    public function getId();

    /**
     * @param  int $id
     *
     * @return $this
     */
    public function setId($id);

    /**
     * Get a Collection of all the Feedback instances associated with this IResponse
     *
     * @return Collection|IFeedback[]
     */
    public function getAllFeedback();

    /**
     * Set a Collection of all the Feedback instances associated with this IResponse
     *
     * @param Collection|IFeedback[] $allFeedback
     * @return $this
     */
    public function setAllFeedback($allFeedback);

    /**
     * Get the person who created this IResponse (typically a student)
     * This -can- be the teacher if they submit on behalf of a student
     *
     * @return User
     */
    public function getSubmittedBy();

    /**
     * Set the person who created this IResponse (typically a student)
     * This -can- be the teacher if they submit on behalf of a student
     *
     * @param User $submittedBy
     * @return $this
     */
    public function setSubmittedBy(User $submittedBy);

    /**
     * Get the person for whom the IResponse was created. This is used when
     * Teachers submit on behalf of Students.
     *
     * submittedOnBehalfOf() is typically the student
     *
     * @return User
     */
    public function getSubmittedOnBehalfOf();

    /**
     * Set the person for whom the IResponse was created
     *
     * @param User $submittedOnBehalfOf
     * @return $this
     */
    public function setSubmittedOnBehalfOf(User $submittedOnBehalfOf);

    /**
     * Get the datetime at which this response was submitted.
     *
     * This is the when:
     * * DueWorkSubmission: transitioned from "draft" to "submitted"
     * * QuizAttempt:       student finished
     * * TaskResponse:      creation
     *
     * @return Alaress_Date_Format
     */
    public function getSubmittedAt();

    /**
     * THIS MEANS "IS NOT DRAFT"
     *
     * @return bool
     */
    public function isSubmitted();

    /**
     * Was this response submitted on time?
     * If the response is not submitted; this will always return false.
     * Therefore:
     *   - "is the response submitted": response.submitted
     *   - "is the response late":      response.submitted AND NOT response.submittedOnTime
     *   - "is the response on time":   response.submitted AND response.submittedOnTime
     *
     * You must not rely on this method without also checking the submission status.
     *
     * @return bool
     */
    public function isSubmittedOnTime();

    /**
     * Does this IResponse have feedback?
     *
     * @deprecated Ask the question whether this has instructor-given or self-assessed feedback instead
     * @return bool
     */
    public function hasFeedback();

    /**
     * Get the IAssessable to which this IResponse belongs
     *
     * @return IAssessable
     */
    public function getAssessable();

    /**
     * Set the IAssessable to which this IResponse belongs
     *
     * @param IAssessable $assessable
     * @return $this
     */
    public function setAssessable($assessable);

    /**
     * Created date
     *
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt();

    /**
     * @param  \Alaress_Date_Format $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(\Alaress_Date_Format $createdAt = null);

    /**
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt();

    /**
     * @param  \Alaress_Date_Format $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt(\Alaress_Date_Format $updatedAt = null);

    /**
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt();

    /**
     * @param  \Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt(\Alaress_Date_Format $deletedAt = null);
}
