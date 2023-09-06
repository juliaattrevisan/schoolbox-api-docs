<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Alaress\Lumberjack\Logger;
use LearnositySdk\Exceptions\ValidationException;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Helper;
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

        // Restore destination before overwriting it?
        if ($destination->isDeleted()) {
            $this->assessmentMapperProxy->restoreSoftDeleted($destination);
        }

        // Overwrite destination fields with values from source
        $updatedDestination = $destination->overwrite($source)
            ->setDeletedAt(null);

        // If copying the assessment into a spec: clear its dates
        if ($destination->isSpecification()) {
            $updatedDestination = $updatedDestination
                ->setPublishAt(null)
                ->setOpenAt(null)
                ->setDueAt(null)
                ->setCloseAt(null);
        }

        // Delete quiz constituents and rubric
        $this->deleteDueWorkRubric($updatedDestination);

        /* Remove this quiz from Learnosity entirely,
         * and orphan it intentionally into an archived state
         */
         $this->deleteQuizConstituents($updatedDestination);

        /* Cloning a quiz means we need the new Learnosity Reference identifier
         * that can only really be obtained after sending it to Learnosity; we
         * store the Learnosity Reference in the $assessment itself, thus making
         * Learnosity content cloning have a sequential dependency here.
         */
        $this->replicateQuizConstituents($source, $updatedDestination);

        // Save
        $this->assessmentMapperProxy->save($updatedDestination, $newOwner);

        // Copy DueWork Rubrics
        $this->replicateDueWorkRubric($source, $updatedDestination);

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

        /* Clone an existing Learnosity Activity
         * Get the cloned activity's Learnosity Reference
         * Assign this new Learnosity Reference to $destination's instance
         * Save it
         */
        $this->quizMapper->copyQuizConstituents($source, $destination);
    }

    /**
     * Delete any quiz sections (and thus questions and solutions) from
     * `$assessment`, in preparation for a new set to be copied into it.
     *
     * @param IAssessable $assessment
     * @return void
     * @throws \DomainException
     * @throws ValidationException
     * @throws \RuntimeException
     */
    private function deleteQuizConstituents(IAssessable $assessment): void
    {
        if (!$assessment instanceof Quiz) {
            return;
        }

        /* $destination (spec) gets overwritten by $source (impl)
         * so we need to orphan its existing quiz thing
         */
        $api = new Api(
            $_SERVER['SERVER_NAME'],
            $this->quizMapper->getConfigLoader()->get('learnosity_consumer_key'),
            $this->quizMapper->getConfigLoader()->get('learnosity_consumer_secret'),
            new Logger(STDOUT, STDOUT, \Monolog\Logger::DEBUG, [], true, 'learnosity')
        );

        (new Helper($api))->archive($assessment->getLearnosityReference());

//        $this->quizMapper->deleteQuizConstituents($assessment->getId());
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
