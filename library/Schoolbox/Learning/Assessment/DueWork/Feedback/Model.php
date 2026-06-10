<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Learning\Assessment\ISelfAssessableFeedback;
use UnexpectedValueException;

/**
 * @method DueWork\Response\Model|null getResponse()
 */
class Model extends AbstractFeedback implements ISelfAssessableFeedback
{
    /**
     * All the Files that are part of the Feedback made by Teacher
     *
     * @var Collection|ReturnedWork\SingleFile\Model[]
     */
    protected $allFiles;

    /**
     * Mark given to this Assessable as shown to the instructor
     *
     * @var string|null
     */
    protected $humanReadableInstructorMark;

    /**
     * Is this feedback marked as requiring resubmission?
     *
     * @var bool
     */
    private $resubmissionRequired = false;

    /**
     * By which work-flow was this feedback created?
     *
     * @var string
     * @see ReturnedWork::REVIEW_ROUTE_INSTRUCTOR and adjacent constants
     */
    private $reviewRoute;


    /**
     * The marked rubric grids for this feedback.
     *
     * @var null|\Schoolbox\GenericCollection<MarkedGrid>
     */
    private ?\Schoolbox\GenericCollection $rubricMarkedGrids = null;


    /**
     * The id of the feedback's publisher, which isn't necessarily
     * the same person as the $reviewer. Currently this is an id,
     * but the naming allows you to hydrate it one day without
     * needing to change heaps of usages
     *
     * @var null|int
     */
    private ?int $publishedBy = null;


    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->allFiles = new Collection();
        $this->rubricMarkedGrids = new \Schoolbox\GenericCollection(MarkedGrid::class);
    }


    /**
     * Returns whether this feedback was given by an instructor in the standard marking work-flow
     *
     * @return bool
     * @throws UnexpectedValueException If method called on an un-hydrated model
     */
    public function isInstructorGiven(): bool
    {
        if (null === $this->reviewRoute) {
            throw new UnexpectedValueException('Unable to determine review route. Property is null');
        }
        return ReturnedWork::REVIEW_ROUTE_INSTRUCTOR === $this->reviewRoute;
    }


    /**
     * Returns whether this feedback was created via one of the self-assessment work-flows
     * (as opposed to merely an instructor marking themselves via the normal grading process)
     *
     * @return bool
     * @throws UnexpectedValueException If model property is not hydrated
     */
    public function isSelfAssessment(): bool
    {
        if (null === $this->reviewRoute) {
            throw new UnexpectedValueException('Unable to determine review route. Property is null');
        }
        $selfAssessedReviewRoutes = [ReturnedWork::REVIEW_ROUTE_SELF_REFLECTION, ReturnedWork::REVIEW_ROUTE_SELF_GRADING];
        return \in_array($this->reviewRoute, $selfAssessedReviewRoutes, true);
    }


    /**
     * Returns whether this feedback was created via the learner self-reflect work-flow
     *
     * @return bool
     * @throws UnexpectedValueException If property is not set
     */
    public function isSelfReflectionOnly(): bool
    {
        if (null === $this->reviewRoute) {
            throw new UnexpectedValueException('Unable to determine review route. Property is null');
        }
        return ReturnedWork::REVIEW_ROUTE_SELF_REFLECTION === $this->reviewRoute;
    }


    /**
     * Returns whether this feedback was created via the learner self-grade work-flow
     *
     * @return bool
     * @throws UnexpectedValueException If model property is not hydrated
     */
    public function isSelfGrading(): bool
    {
        if (null === $this->reviewRoute) {
            throw new UnexpectedValueException('Unable to determine review route. Property is null');
        }
        return ReturnedWork::REVIEW_ROUTE_SELF_GRADING === $this->reviewRoute;
    }


    /* Getters/Setters
     */

    /**
     * @return Collection|ReturnedWork\SingleFile\Model[]
     */
    public function getAllFiles()
    {
        return $this->allFiles;
    }


    /**
     * @param Collection|ReturnedWork\SingleFile\Model[] $allFiles
     * @return self
     */
    public function setAllFiles($allFiles)
    {
        $this->allFiles = $allFiles;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getHumanReadableInstructorMark()
    {
        return $this->humanReadableInstructorMark;
    }


    /**
     * @param string|null $humanReadableInstructorMark
     * @return $this
     */
    public function setHumanReadableInstructorMark($humanReadableInstructorMark)
    {
        $this->humanReadableInstructorMark = $humanReadableInstructorMark;
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
     * @return null|\Schoolbox\GenericCollection<MarkedGrid>
     */
    public function getRubricMarkedGrids(): ?\Schoolbox\GenericCollection
    {
        return $this->rubricMarkedGrids;
    }


    /**
     * @param \Schoolbox\GenericCollection<MarkedGrid> $rubricMarkedGrids
     * @return static
     */
    public function setRubricMarkedGrids(\Schoolbox\GenericCollection $rubricMarkedGrids): self
    {
        $this->rubricMarkedGrids = $rubricMarkedGrids;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getPublishedBy(): ?int
    {
        return $this->publishedBy;
    }


    /**
     * @param null|int $publishedBy
     * @return $this
     */
    public function setPublishedBy(?int $publishedBy): self
    {
        $this->publishedBy = $publishedBy;
        return $this;
    }


    /**
     * Checks whether is empty, excluding Rubric
     *
     * A feedback is empty only if all these conditions are true:
     * * It has no files
     * * It has no normalized mark
     * * It has no human-readable mark
     * * It has no comment
     * * It is not marked as requiring resubmission
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return ($this->getAllFiles()->count() === 0)
            && ($this->getNormalisedMark() === null)
            && ($this->getHumanReadableMark() === null || trim($this->getHumanReadableMark()) === '')
            && ($this->getHumanReadableInstructorMark() === null || trim($this->getHumanReadableInstructorMark()) === '')
            && ($this->getComment() === null || trim($this->getComment()) === '')
            && !$this->hasResponseWithKamiDocs()
            && !$this->resubmissionRequired;
    }


    /**
     * Returns whether this feedback may represent kami comments
     *
     * For now, we cannot know this conclusively in SB without hitting the Kami API.
     *
     * Returns TRUE if feedback is for a submission that has files that have been sent to Kami,
     * regardless of whether those Kami docs have actually been annotated
     *
     * @return bool
     */
    public function hasResponseWithKamiDocs(): bool
    {
        $response = $this->getResponse();
        // Floating feedback?
        if (!$response) {
            // Cannot have annotations associated
            return false;
        }
        foreach ($response->getAllFiles() as $responseFile) {
            $kami = $responseFile->getKami();
            if ($kami && $kami->isStatusDone()) {
                // There *may* be comments here
                return true;
            }
        }
        return false;
    }
}
