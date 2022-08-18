<?php

/**
 * IReview
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\User;

interface IFeedback
{
    /**
     * Determine whether this Feedback has been published.
     * If the publish date is null, it's a draft
     *
     * @return bool
     */
    public function isPublished();


    /**
     * Has this feedback not yet been published?
     *
     * @return bool
     */
    public function isDraft();


    /* Getters/Setters
     */

    /**
     * @return int
     */
    public function getId();

    /**
     * @param  int $id
     *
     * @return $this
     */
    public function setId($id);

    /**
     * Get the human-readable representation of the Normalised Mark
     *
     * This returns a string which could be something like "Not Submitted"
     * or "B+" or "32/36"
     *
     * @return string
     */
    public function getHumanReadableMark();

    /**
     * Set the human-readable representation of the Normalised Mark
     *
     * @param string $humanReadableMark
     * @return $this
     */
    public function setHumanReadableMark($humanReadableMark);

    /**
     * Get the Normalised Mark for an IFeedback
     *
     * This is essentially a value out of 100 which numerically represents
     * the human-readable mark (and vice versa)
     *
     * @return float|null
     */
    public function getNormalisedMark();

    /**
     * Set the Normalised Mark for an IFeedback
     *
     * @param null|float $normalisedMark
     * @return $this
     */
    public function setNormalisedMark($normalisedMark);

    /**
     * Get the comment associated with this IFeedback
     *
     * @return string
     */
    public function getComment();

    /**
     * Set the comment associated with this IFeedback
     *
     * @param string $comment
     * @return $this
     */
    public function setComment($comment);

    /**
     * Get the person for whom this IFeedback was created
     *
     * Typically a student
     *
     * @return User
     */
    public function getReviewee(): User;

    /**
     * Set the person for whom this IFeedback was created
     *
     * @param User $reviewee
     * @return $this
     */
    public function setReviewee(User $reviewee): self;

    /**
     * Get the person who created this IFeedback
     *
     * Typically a teacher, but may be null if the creator is unknown.
     *
     * @return User|null
     */
    public function getReviewer(): ?User;

    /**
     * Set the person who created this IFeedback
     *
     * Typically a teacher, but may be null if the creator is unknown.
     *
     * @param User|null $reviewer
     * @return $this
     */
    public function setReviewer(?User $reviewer): self;

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
     * @return IResponse|null
     */
    public function getResponse();

    /**
     * @param IResponse|null $response
     * @return static
     */
    public function setResponse(IResponse $response = null);

    /**
     * Get the date when this IFeedback was made accessible to respondents
     * (typically students)
     *
     * A value of null or instance of Alaress_Date_NullDate indicates that
     * this IFeedback was never published
     *
     * @return \Alaress_Date_Format
     */
    public function getPublishedAt();

    /**
     * Set the date when this IFeedback was made accessible to respondents
     *
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
