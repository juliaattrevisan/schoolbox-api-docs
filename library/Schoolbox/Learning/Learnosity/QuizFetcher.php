<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Schoolbox\Learning\Quiz\Model as Quiz;

class QuizFetcher
{
    /**
     * Constructor.
     *
     * @param \Schoolbox\Learning\Quiz\Mapper $quizMapper
     */
    public function __construct(private \Schoolbox\Learning\Quiz\Mapper $quizMapper)
    {
    }


    /**
     * Converts an assessment_id to a Quiz
     *
     * Literally copypasta'd
     *
     * @param int $assessmentId
     * @return Quiz
     * @throws \RuntimeException
     */
    public function findByAssessmentId(int $assessmentId): Quiz
    {
        /** @var null|Quiz $quiz */
        $quiz = $this->quizMapper->findByColumn('assessment_id', $assessmentId, ['deleted' => true])->first();

        /* Not a quiz
         */
        if (null === $quiz) {
            throw new \RuntimeException(sprintf(
                'Assessment id %s is not a quiz.',
                $assessmentId
            ));
        }

        if (Quiz::AVAILABILITY_ASSESS !== $quiz->getAvailability()) {
            throw new \RuntimeException(sprintf(
                'Assessment id %s is somehow not associated with a quiz.',
                $assessmentId
            ));
        }

        return $quiz;
    }
}
