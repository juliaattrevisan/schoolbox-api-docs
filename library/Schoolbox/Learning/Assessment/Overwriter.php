<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\User;

/**
 * Helper class for overwriting one assessment with the content of another.
 */
class Overwriter
{
    /**
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param QuizMapper            $quizMapper
     * @param RubricDataSource      $rubricMapper
     */
    public function __construct(
        private AssessmentMapperProxy $assessmentMapperProxy,
        private QuizMapper $quizMapper,
        private RubricDataSource $rubricMapper
    ) {
    }

    /**
     * Overwrite `$destination` with appropriate fields from `$source`.
     *
     * @param IAssessable $source
     * @param IAssessable $destination
     * @param User        $newOwner
     * @return IAssessable
     */
    public function overwrite(
        IAssessable $source,
        IAssessable $destination,
        User $newOwner,
    ): IAssessable {
        // Must assert that source and destination are the same sort of thing;
        // otherwise who knows what disaster could happen
        if (!($source->getFamilyName() === $destination->getFamilyName())) {
            throw new \RuntimeException(
                sprintf(
                    '$source (%s) and $destination (%s) must be the same family of assessable',
                    $source->getFamilyName(),
                    $destination->getFamilyName(),
                )
            );
        }

        // Get fields from destination:
        $updatedDestination = $source->cloneForCopy();
        $updatedDestination
            ->setId($destination->getId())
            ->setSpecificationAssessment($destination->getSpecificationAssessment())
            ->setContext($destination->getContext())
            ->setSequence($destination->getSequence())
            ->setDeletedAt(new \Alaress_Date_NullDate());

        // If copying the assessment into a spec: clear its dates
        if ($destination->isSpecification()) {
            $nullDate           = new \Alaress_Date_NullDate();
            $updatedDestination = $updatedDestination
                ->setPublishAt($nullDate)
                ->setOpenAt($nullDate)
                ->setDueAt($nullDate)
                ->setCloseAt($nullDate);
        }

        // Delete quiz constituents and rubric
        $this->deleteDueWorkRubric($updatedDestination);
        $this->deleteQuizConstituents($updatedDestination);

        // Save
        $this->assessmentMapperProxy->save($updatedDestination, $newOwner);

        // Copy DueWork Rubrics
        $this->replicateDueWorkRubric($source, $updatedDestination);

        // Copy Quiz Sections/Questions/Solutions
        $this->replicateQuizConstituents($source, $updatedDestination);

        return $updatedDestination;
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
     * Delete any quiz sections (and thus questions and solutions) from
     * `$assessment`, in preparation for a new set to be copied into it.
     *
     * @param IAssessable $assessment
     * @return void
     */
    private function deleteQuizConstituents(IAssessable $assessment): void
    {
        if (!$assessment instanceof Quiz) {
            return;
        }
        $this->quizMapper->deleteQuizConstituents($assessment->getId());
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

    /**
     * Delete the rubric from `$assessment`, in preparation for a new rubric to
     * be copied into it.
     *
     * @param IAssessable $assessment
     * @return void
     */
    private function deleteDueWorkRubric(IAssessable $assessment): void
    {
        if (!$assessment instanceof DueWork) {
            return;
        }
        $this->rubricMapper->deleteRubric($assessment->getId());
    }
}
