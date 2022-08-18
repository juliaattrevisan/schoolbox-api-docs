<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\User;

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
     * @var RubricDataSource
     */
    private $rubricMapper;

    /**
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param QuizMapper            $quizMapper
     * @param RubricDataSource      $rubricMapper
     */
    public function __construct(
        AssessmentMapperProxy $assessmentMapperProxy,
        QuizMapper $quizMapper,
        RubricDataSource $rubricMapper
    ) {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->quizMapper = $quizMapper;
        $this->rubricMapper = $rubricMapper;
    }


    /**
     * Deep-clone and re-create the given assessments in a new context
     *
     * To aid processing, the caller should populate dates on source Assessments, even
     * if the source Assessments are Specifications. This avoids needing to pass the dates in
     * a custom DTO.
     *
     * @param IAssessable       $source             the assessment to replicate
     * @param AssessmentContext $destinationContext the location into which to replicate the assessment
     * @param User              $newOwner           who should own the newly created assessment
     * @param null|int          $sequence           enable explicit sequence setting
     * @return IAssessable if any part of the replication fails
     * @throws \InvalidArgumentException If Destination Quiz already has Sections
     * @throws \UnexpectedValueException
     */
    public function replicateInNewContext(
        IAssessable $source,
        AssessmentContext $destinationContext,
        User $newOwner,
        int $sequence = null
    ): IAssessable {
        $newAssessment = $source->cloneForCopy();
        $newAssessment->setContext($destinationContext);

        // If copying the assessment into a spec: clear its dates
        if ($destinationContext->isSpecificationContext()) {
            $nullDate      = new \Alaress_Date_NullDate();
            $newAssessment = $newAssessment
                ->setPublishAt($nullDate)
                ->setOpenAt($nullDate)
                ->setDueAt($nullDate)
                ->setCloseAt($nullDate);
        }

        $destinationContext->getAssessables()->add($newAssessment);
        $newAssessment->setCreator($newOwner);
        if (null !== $sequence) {
            $newAssessment->setSequence($sequence);
        }

        $this->assessmentMapperProxy->save($newAssessment, $newOwner);
        $destinationContext->getAssessables()->add($newAssessment);

        // Copy DueWork Rubrics
        $this->replicateDueWorkRubric($source, $newAssessment);

        // Copy Quiz Sections/Questions/Solutions
        $this->replicateQuizConstituents($source, $newAssessment);

        return $newAssessment;
    }

    /**
     * Copy any quiz sections, questions and solutions to `$destination` from
     * `$source`.
     *
     * @param IAssessable $source
     * @param IAssessable $destination
     * @return void
     * @throws \InvalidArgumentException If Destination Quiz already has Sections
     */
    private function replicateQuizConstituents(IAssessable $source, IAssessable $destination): void
    {
        if (!($source instanceof Quiz && $destination instanceof Quiz)) {
            return;
        }
        $this->quizMapper->copyQuizConstituents($source->getId(), $destination->getId());
    }

    /**
     * Copy the rubric from `$source` into `$destination`.
     *
     * @param IAssessable $source
     * @param IAssessable $destination
     * @return void
     */
    private function replicateDueWorkRubric(IAssessable $source, IAssessable $destination): void
    {
        if (!($source instanceof DueWork && $destination instanceof DueWork)) {
            return;
        }
        $this->rubricMapper->copyRubric($source->getId(), $destination->getId());
    }
}
