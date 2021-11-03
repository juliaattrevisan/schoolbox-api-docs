<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Assessment\Score;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\LTI\Assessment\Model as LTIAssessmentModel;
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

    /**
     * Activity progress values which are considered to be responses from the
     * student.
     *
     * If this score has this value, and the previous score for this
     * student+assessment has a different value, then this is a response.
     */
    public const ACTIVITY_PROGRESS_RESPONSE_VALUES = [
        self::ACTIVITY_PROGRESS_SUBMITTED,
        self::ACTIVITY_PROGRESS_COMPLETED,
    ];

    /**
     * From https://www.imsglobal.org/spec/lti-ags/v2p0/#activityprogress:
     *
     * > the user has not started the activity, or the activity has been reset
     * > for that student.
     */
    public const ACTIVITY_PROGRESS_INITIALIZED = 'Initialized';

    /**
     * From https://www.imsglobal.org/spec/lti-ags/v2p0/#activityprogress:
     *
     * > the activity associated with the line item has been started by the user
     * > to which the result relates.
     */
    public const ACTIVITY_PROGRESS_STARTED = 'Started';

    /**
     * From https://www.imsglobal.org/spec/lti-ags/v2p0/#activityprogress:
     *
     * > the activity is being drafted and is available for comment.
     */
    public const ACTIVITY_PROGRESS_IN_PROGRESS = 'InProgress';

    /**
     * From https://www.imsglobal.org/spec/lti-ags/v2p0/#activityprogress:
     *
     * > the activity has been submitted at least once by the user but the user
     * > is still able to make further submissions.
     */
    public const ACTIVITY_PROGRESS_SUBMITTED = 'Submitted';

    /**
     * From https://www.imsglobal.org/spec/lti-ags/v2p0/#activityprogress:
     *
     * > the user has completed the activity associated with the line item.
     */
    public const ACTIVITY_PROGRESS_COMPLETED = 'Completed';

    /**
     * Valid values for grading progress.
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

    /**
     * Grading progress values which are considered to be feedback from the
     * instructor.
     *
     * If this score has this value, and the previous score for this
     * student+assessment has a different value, then this is a feedback.
     */
    public const GRADING_PROGRESS_FEEDBACK_VALUES = [
        self::GRADING_PROGRESS_PENDING,
        self::GRADING_PROGRESS_PENDING_MANUAL,
        self::GRADING_PROGRESS_FULLY_GRADED,
    ];

    /**
     * From https://www.imsglobal.org/spec/lti-ags/v2p0/#gradingprogress:
     *
     * > There is no grading process occurring; for example, the student has not
     * > yet made any submission.
     */
    public const GRADING_PROGRESS_NOT_READY = 'NotReady';

    /**
     * From https://www.imsglobal.org/spec/lti-ags/v2p0/#gradingprogress:
     *
     * > The grading could not complete.
     */
    public const GRADING_PROGRESS_FAILED = 'Failed';

    /**
     * From https://www.imsglobal.org/spec/lti-ags/v2p0/#gradingprogress:
     *
     * > Final Grade is pending, but does not require manual intervention; if a
     * > Score value is present, it indicates the current value is partial and
     * > may be updated.
     */
    public const GRADING_PROGRESS_PENDING = 'Pending';

    /**
     * From https://www.imsglobal.org/spec/lti-ags/v2p0/#gradingprogress:
     *
     * > Final Grade is pending, and it does require human intervention; if a
     * > Score value is present, it indicates the current value is partial and
     * > may be updated during the manual grading.
     */
    public const GRADING_PROGRESS_PENDING_MANUAL = 'PendingManual';

    /**
     * From https://www.imsglobal.org/spec/lti-ags/v2p0/#gradingprogress:
     *
     * > The grading process is completed; the score value, if any, represents
     * > the current Final Grade;
     */
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
     * The time the score was created in the LTI tool.
     *
     * Distinct from `$this->createdAt`: that field stores the time this record
     * was created in Schoolbox, this field stores when the tool created it.
     *
     * @see https://www.imsglobal.org/spec/lti-ags/v2p0/#timestamp
     */
    private Alaress_Date_Format $submittedAt;

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
     * Determine whether this score's activity progress value makes this score a
     * response, given the previous score.
     *
     * @param Model|null $previous
     * @return bool
     */
    public function determineResponseStatus(?Model $previous): bool
    {
        if (in_array($this->activityProgress, self::ACTIVITY_PROGRESS_RESPONSE_VALUES)) {
            if (!$previous) {
                return true;
            }
            return $this->activityProgress !== $previous->getActivityProgress();
        }
        return false;
    }

    /**
     * Determine whether this score's activity progress value makes this score a
     * feedback, given the previous score.
     *
     * @param Model|null $previous
     * @return bool
     */
    public function determineFeedbackStatus(?Model $previous): bool
    {
        if (in_array($this->gradingProgress, self::GRADING_PROGRESS_FEEDBACK_VALUES)) {
            if (!$previous) {
                return true;
            }
            return $this->gradingProgress !== $previous->getGradingProgress();
        }
        return false;
    }

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
     * @return Alaress_Date_Format
     */
    public function getSubmittedAt(): Alaress_Date_Format
    {
        return $this->submittedAt;
    }

    /**
     * @param Alaress_Date_Format $submittedAt
     * @return $this
     */
    public function setSubmittedAt(Alaress_Date_Format $submittedAt): self
    {
        $this->submittedAt = $submittedAt;
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
