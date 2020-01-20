<?php
/**
 * Model for teacher returns for student submissions into submission boxes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Mapper\Traits;
use Schoolbox\User;
use Schoolbox\UserMapper;
use \Schoolbox\Mapper\Traits\Model\Id;
use \Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use \Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use \Schoolbox\Mapper\Traits\Model\DeletedAtDate;


class ReturnedWork implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    // By which work-flow did this feedback come into existence?
    public const REVIEW_ROUTE_INSTRUCTOR      = 'instructor';      // normal teacher marking a student
    public const REVIEW_ROUTE_SELF_REFLECTION = 'self-reflection'; // learner self-reflection
    public const REVIEW_ROUTE_SELF_GRADING    = 'self-grading';    // learner self-grading


    /**
     * @var SubmissionBox
     */
    protected $submission_box;

    /**
     * The person who marked the work
     *
     * @var User
     */
    protected $marker;

    /**
     * The person who the mark is for
     *
     * @var User
     */
    protected $owner;

    /**
     * This Return is optionally linked to a Submission
     *
     * @var SubmissionBox\Submission\Submission|null;
     */
    protected $submission;

    /**
     * @var string
     */
    protected $comment;

    /**
     * @var null|string
     */
    protected $humanReadableMark;

    /**
     * @var null|float
     */
    protected $normalized_mark;


    /**
     * @var bool
     */
    private $resubmissionRequired = false;


    /**
     * @var \Alaress_Date_Format
     */
    protected $publishedAt;

    /**
     * @var ReturnedWork\SingleFile\Model[]|Collection
     */
    private $files;

    /**
     * By which work-flow was this review created?
     *
     * @var string
     * @see ReturnedWork::REVIEW_ROUTE_INSTRUCTOR
     * @see ReturnedWork::REVIEW_ROUTE_SELF_REFLECTION
     * @see ReturnedWork::REVIEW_ROUTE_SELF_GRADING
     */
    private $reviewRoute;


    /**
     * Creates object and sets up related objects.
     */
    public function __construct()
    {
        $this->setSubmissionBox(new SubmissionBox());
        $this->setOwner(new User());
        $this->files = new Collection();
    }

    /**
     * Calculates a gradient (from 0 to 10) from the normalized mark for this
     * return.
     *
     * @return int|null
     */
    public function getGradientFromNormalizedMark()
    {
        if (!empty($this->getNormalizedMark())) {
            return max(0, min(10, (int)($this->getNormalizedMark() / 10)));
        }
        return null;
    }

    /**
     * Does this user have permission to view this return?
     *
     * * Users may view their own returns
     * * Staff may view returns
     * * Parents may view their child's returns
     *
     * @param  User $user
     * @return bool
     */
    public function hasPermission(User $user)
    {
        if ($user->isSuperuser()) {
            return true;
        }
        if ($user->getId() == $this->getOwner()->getId()) {
            return true;
        }
        if ($user->getRole()->isStaff()) {
            return true;
        }
        if ($user->getRole()->isParent()) {
            $this->setOwner(UserMapper::getInstance()->findById($this->getOwner()->getId()));
            $this->getOwner()->loadParents();
            if ($this->getOwner()->isChildOf($user)) {
                return true;
            }
        }
        return false;
    }

    /* Getters/Setters
     */

    /**
     * @return \Schoolbox\Assessment\Component\SubmissionBox
     */
    public function getSubmissionBox()
    {
        return $this->submission_box;
    }

    /**
     * @param  \Schoolbox\Assessment\Component\SubmissionBox $submission_box
     * @return $this
     */
    public function setSubmissionBox($submission_box)
    {
        $this->submission_box = $submission_box;
        return $this;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getMarker()
    {
        return $this->marker;
    }

    /**
     * @param \Schoolbox\User $marker
     * @return $this
     */
    public function setMarker($marker)
    {
        $this->marker = $marker;
        return $this;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param  \Schoolbox\User $owner
     * @return $this
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return null|Submission\Submission
     */
    public function getSubmission()
    {
        return $this->submission;
    }

    /**
     * @param Submission\Submission|null $submission
     * @return static
     */
    public function setSubmission(Submission\Submission $submission = null)
    {
        $this->submission = $submission;
        return $this;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param  string $comment
     * @return $this
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getHumanReadableMark()
    {
        return $this->humanReadableMark;
    }

    /**
     * @param  null|string $mark
     * @return $this
     */
    public function setHumanReadableMark($mark)
    {
        $this->humanReadableMark = $mark;
        return $this;
    }

    /**
     * @return null|float
     */
    public function getNormalizedMark()
    {
        return $this->normalized_mark;
    }

    /**
     * @param  null|float $normalized_mark
     * @return $this
     */
    public function setNormalizedMark($normalized_mark)
    {
        $this->normalized_mark = $normalized_mark;
        return $this;
    }


    /**
     * @return bool
     */
    public function getResubmissionRequired(): bool
    {
        return $this->resubmissionRequired;
    }


    /**
     * @param bool $resubmissionRequired
     * @return static
     */
    public function setResubmissionRequired(bool $resubmissionRequired): self
    {
        $this->resubmissionRequired = $resubmissionRequired;
        return $this;
    }


    /**
     * Is this a draft?
     *
     * @return bool
     */
    public function isDraft()
    {
        return !$this->isPublished();
    }

    /**
     * Is this return published?
     *
     * @return bool
     */
    public function isPublished()
    {
        return ($this->getPublishedAt() instanceof \Alaress_Date_Format && !$this->getPublishedAt()->isNull());
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getPublishedAt()
    {
        return $this->publishedAt;
    }

    /**
     * @param  \Alaress_Date_Format $publishedAt
     *
     * @return static
     */
    public function setPublishedAt($publishedAt)
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    /**
     * @return ReturnedWork\SingleFile\Model[]|Collection
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param ReturnedWork\SingleFile\Model[]|Collection $files
     * @return $this
     */
    public function setFiles(Collection $files)
    {
        $this->files = $files;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getReviewRoute(): ?string
    {
        return $this->reviewRoute;
    }


    /**
     * @param string $reviewRoute
     * @return $this
     */
    public function setReviewRoute(string $reviewRoute): self
    {
        $this->reviewRoute = $reviewRoute;
        return $this;
    }

    /**
     * @return bool
     * @throws InsufficientHydrationException
     */
    public function isSelfAssess(): bool
    {
        if (null === $this->reviewRoute) {
            throw new InsufficientHydrationException('Review route is not set');
        }

        return in_array(
            $this->reviewRoute,
            [
                self::REVIEW_ROUTE_SELF_REFLECTION,
                self::REVIEW_ROUTE_SELF_GRADING
            ],
            true
        );
    }
}
