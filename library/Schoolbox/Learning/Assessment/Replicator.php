<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use ICollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Mapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;

/**
 * Responsible for:
 * 1. Replicating Assessments in a new Context
 * 2. Moving Assessments between Contexts
 */
class Replicator
{
    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @var QuizMapper
     */
    private $quizMapper;

    /**
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param QuizMapper $quizMapper
     */
    public function __construct(AssessmentMapperProxy $assessmentMapperProxy, QuizMapper $quizMapper)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->quizMapper = $quizMapper;
    }

    /**
     * Deep-clone and re-create the given assessments in a new context
     *
     * @param ICollection|IAssessable[] $assessments the assessments to replicate
     * @param AssessmentContext         $destination the location into which to replicate the assessments
     * @throws Mapper\Exception                      if any part of the replication fails
     * @return ICollection|IAssessable[]             the NEW assessments, as replicated
     */
    public function replicateInNewContext(ICollection $assessments, AssessmentContext $destination)
    {
        $newAssessments = $this->assessmentMapperProxy->createCollection();

        foreach($assessments as $assessment){
            $newAssessment = $assessment->cloneForCopy();
            $newAssessment->setContext($destination);

            // TODO SA-1698 need to implement __clone() on DueWork to clone Rubric correctly
            $this->replicateDueWorkRubric($newAssessment);

            $this->setProjectAssessableDates($newAssessment);
            $this->assessmentMapperProxy->save($newAssessment);

            // Copy Quiz Sections/Questions/Solutions
            $this->replicateQuizConstituents($newAssessment);

            $destination->getAssessables()->add($newAssessment);
            $newAssessments->add($newAssessment);
        }
        return $newAssessments;
    }

    /**
     * Set the dates on any Assessables inside a Project
     *
     * Can recursively handle any project nesting
     *
     * @param IAssessable $assessable
     */
    protected function setProjectAssessableDates(IAssessable $assessable)
    {
        // Base case #1
        if(!$assessable instanceof Project){
            return;
        }

        // Recursive case
        if($assessable instanceof Project){
            foreach($assessable->getAssessables() as $subAssessable)
            {
                // TODO - SA-1638 - Need to set some to null but out of scope for this PR
                $subAssessable->setPublishAt($assessable->getPublishAt());
                $subAssessable->setOpenAt($assessable->getPublishAt());
                $subAssessable->setDueAt($assessable->getDueAt());
                $subAssessable->setCloseAt($assessable->getDueAt());
                $this->setProjectAssessableDates($subAssessable);
            }
        }
    }

    /**
     * Copy any Quiz Sections, Questions + Solutions to $assessable from its Specification
     *
     * Can handle when $assessable is a Quiz, or any level of Project (via Recursion)
     *
     * @param IAssessable $assessable
     */
    protected function replicateQuizConstituents(IAssessable $assessable)
    {
        // Recursive case
        if($assessable instanceof Project){
            foreach ($assessable->getAssessables() as $subAssessable) {
                $this->replicateQuizConstituents($subAssessable);
            }
        }

        // Base case #1
        if(!$assessable instanceof Quiz){
            return;
        }

        // Base case #2
        $specId = $assessable->getSpecificationAssessment()->getId();
        $implId = $assessable->getId();
        $this->quizMapper->copyQuizConstituents($specId, $implId);
    }

    /**
     * Correctly clone a DueWork Rubric
     *
     * Can handle when $assessable is a DueWork, or any level of Project (via Recursion)
     *
     * @param IAssessable $assessable
     */
    protected function replicateDueWorkRubric(IAssessable $assessable)
    {
        // Recursive case
        if($assessable instanceof Project){
            foreach ($assessable->getAssessables() as $projectAssessable) {
                $this->replicateDueWorkRubric($projectAssessable);
            }
        }

        // Base case #1
        if(!$assessable instanceof DueWork){
            return;
        }

        // Base case #2
        // TODO - clone the Rubric correctly
    }

    /**
     * Move the given assessments from their current context to a new context
     *
     * Assessments must be Implementations, not Specifications.
     * That is, they cannot be moved from or to a UnitSpecification
     *
     * @param ICollection|IAssessable[] $assessments the assessments to move
     * @param AssessmentContext         $destination the location into which to move the assessments
     * @throws Mapper\Exception                      if any part of the move fails
     * @return ICollection|IAssessable[]             the NEW assessments, as moved
     */
    public function moveToNewContext(ICollection $assessments, AssessmentContext $destination)
    {
        // TODO - implement me
        throw new \BadMethodCallException('Not yet implemented.');
    }
}