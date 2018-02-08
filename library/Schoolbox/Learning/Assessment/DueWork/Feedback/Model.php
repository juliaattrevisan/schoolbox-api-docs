<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use \Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use UnexpectedValueException;

class Model extends AbstractFeedback
{

    /**
     * All the Files that are part of the Feedback made by Teacher
     *
     * @var Collection|File\Model[]
     */
    protected $allFiles;

    /**
     * By which work-flow was this feedback created?
     *
     * @var string
     * @see ReturnedWork::REVIEW_ROUTE_INSTRUCTOR and adjacent constants
     */
    private $reviewRoute;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->setAllFiles(new Collection());
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
     * @return Collection|File\Model[]
     */
    public function getAllFiles()
    {
        return $this->allFiles;
    }

    /**
     * @param Collection|File\Model[] $allFiles
     * @return self
     */
    public function setAllFiles($allFiles)
    {
        $this->allFiles = $allFiles;
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
}
