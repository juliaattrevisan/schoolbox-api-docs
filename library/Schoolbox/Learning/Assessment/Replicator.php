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
 * Responsible for replicating Assessments in a new Context
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
     * To aid processing, the caller should populate dates on source Assessments, even
     * if the source Assessments are Specifications. This avoids needing to pass the dates in
     * a custom DTO.
     *
     * @param IAssessable       $assessmentSpec  the assessments to replicate
     * @param AssessmentContext $destination     the location into which to replicate the assessments
     * @throws Mapper\Exception                  if any part of the replication fails
     * @return IAssessable                       the NEW assessments, as replicated
     */
    public function replicateInNewContext(IAssessable $assessmentSpec, AssessmentContext $destination)
    {
        $newAssessment = $assessmentSpec->cloneForCopy();
        $newAssessment->setContext($destination);

        // TODO SA-1698 need to implement __clone() on DueWork to clone Rubric correctly
        $this->replicateDueWorkRubric($newAssessment);

        $this->assessmentMapperProxy->save($newAssessment);

        // Copy Quiz Sections/Questions/Solutions
        $this->replicateQuizConstituents($newAssessment);
        $destination->getAssessables()->add($newAssessment);
        return $newAssessment;
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
        if(!$assessable instanceof Quiz){
            return;
        }

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
        // Base case #1
        if(!$assessable instanceof DueWork){
            return;
        }

        // Base case #2
        // TODO - SA-1698 clone the Rubric correctly
    }
}