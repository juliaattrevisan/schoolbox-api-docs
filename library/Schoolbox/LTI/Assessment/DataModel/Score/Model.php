<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Assessment\DataModel\Score;

use Schoolbox\ISoftDeletable;
use Schoolbox\LTI\Assessment\DataModel\Model as LTIAssessmentModel;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\User;

/**
 * LTI assessment score model.
 *
 * Expected to be created when Schoolbox receives a message from an LTI tool's
 * Score Publish service:
 * @see https://www.imsglobal.org/spec/lti-ags/v2p0/#score-publish-service
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * Valid values for activity progress.
     *
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0/#activityprogress
     */
    public const ACTIVITY_PROGRESS_VALID_VALUES = [
        self::ACTIVITY_PROGRESS_INITIALIZED,
        self::ACTIVITY_PROGRESS_STARTED,
        self::ACTIVITY_PROGRESS_IN_PROGRESS,
        self::ACTIVITY_PROGRESS_SUBMITTED,
        self::ACTIVITY_PROGRESS_COMPLETED,
    ];
    public const ACTIVITY_PROGRESS_INITIALIZED = 'Initialized';
    public const ACTIVITY_PROGRESS_STARTED = 'Started';
    public const ACTIVITY_PROGRESS_IN_PROGRESS = 'InProgress';
    public const ACTIVITY_PROGRESS_SUBMITTED = 'Submitted';
    public const ACTIVITY_PROGRESS_COMPLETED = 'Completed';

    /**
     * Valid values for activity progress.
     *
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0/#gradingprogress
     */
    public const GRADING_PROGRESS_VALID_VALUES = [
        self::GRADING_PROGRESS_NOT_READY,
        self::GRADING_PROGRESS_FAILED,
        self::GRADING_PROGRESS_PENDING,
        self::GRADING_PROGRESS_PENDING_MANUAL,
        self::GRADING_PROGRESS_FULLY_GRADED,
    ];
    public const GRADING_PROGRESS_NOT_READY = 'NotReady';
    public const GRADING_PROGRESS_FAILED = 'Failed';
    public const GRADING_PROGRESS_PENDING = 'Pending';
    public const GRADING_PROGRESS_PENDING_MANUAL = 'PendingManual';
    public const GRADING_PROGRESS_FULLY_GRADED = 'FullyGraded';

    /**
     * The LTI assessment to which this score is attached.
     */
    private LTIAssessmentModel $ltiAssessment;

    /**
     * The student to whom this score is given.
     */
    private User $student;

    /**
     * The teacher or other user which gave this score (if known).
     */
    private ?User $markingUser = null;

    /**
     * The score which was given to the student, if any.
     */
    private ?float $scoreGiven = null;

    /**
     * The maximum possible score available for the assessment at time of
     * scoring, if provided.
     */
    private ?float $scoreMaximum = null;

    /**
     * A comment on the score, if any.
     */
    private ?string $comment = null;

    /**
     * The student's progress toward completing the activity.
     */
    private string $activityProgress;

    /**
     * The marking user's progress toward marking the student.
     */
    private string $gradingProgress;

    /**
     * Is this score considered a valid assessment response by Schoolbox?
     */
    private bool $isResponse = false;

    /**
     * Is this score considered a valid assessment feedback by Schoolbox?
     */
    private bool $isFeedback = false;

    /**
     * @return LTIAssessmentModel
     */
    public function getLtiAssessment(): LTIAssessmentModel
    {
        return $this->ltiAssessment;
    }

    /**
     * @param LTIAssessmentModel $ltiAssessment
     * @return $this
     */
    public function setLtiAssessment(LTIAssessmentModel $ltiAssessment): self
    {
        $this->ltiAssessment = $ltiAssessment;
        return $this;
    }

    /**
     * @return User
     */
    public function getStudent(): User
    {
        return $this->student;
    }

    /**
     * @param User $student
     * @return $this
     */
    public function setStudent(User $student): self
    {
        $this->student = $student;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getMarkingUser(): ?User
    {
        return $this->markingUser;
    }

    /**
     * @param User|null $markingUser
     * @return $this
     */
    public function setMarkingUser(?User $markingUser): self
    {
        $this->markingUser = $markingUser;
        return $this;
    }

    /**
     * @return float|null
     */
    public function getScoreGiven(): ?float
    {
        return $this->scoreGiven;
    }

    /**
     * @param float|null $scoreGiven
     * @return $this
     */
    public function setScoreGiven(?float $scoreGiven): self
    {
        $this->scoreGiven = $scoreGiven;
        return $this;
    }

    /**
     * @return float|null
     */
    public function getScoreMaximum(): ?float
    {
        return $this->scoreMaximum;
    }

    /**
     * @param float|null $scoreMaximum
     * @return $this
     */
    public function setScoreMaximum(?float $scoreMaximum): self
    {
        $this->scoreMaximum = $scoreMaximum;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @param string|null $comment
     * @return $this
     */
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return string
     */
    public function getActivityProgress(): string
    {
        return $this->activityProgress;
    }

    /**
     * @param string $activityProgress
     * @return $this
     */
    public function setActivityProgress(string $activityProgress): self
    {
        $this->activityProgress = $activityProgress;
        return $this;
    }

    /**
     * @return string
     */
    public function getGradingProgress(): string
    {
        return $this->gradingProgress;
    }

    /**
     * @param string $gradingProgress
     * @return $this
     */
    public function setGradingProgress(string $gradingProgress): self
    {
        $this->gradingProgress = $gradingProgress;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsResponse(): bool
    {
        return $this->isResponse;
    }

    /**
     * @param bool $isResponse
     * @return $this
     */
    public function setIsResponse(bool $isResponse): self
    {
        $this->isResponse = $isResponse;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsFeedback(): bool
    {
        return $this->isFeedback;
    }

    /**
     * @param bool $isFeedback
     * @return $this
     */
    public function setIsFeedback(bool $isFeedback): self
    {
        $this->isFeedback = $isFeedback;
        return $this;
    }
}
