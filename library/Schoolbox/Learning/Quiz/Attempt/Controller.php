<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Quiz\Attempt;

use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainObject;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * Constructor.
     */
    public function __construct(
        private \Schoolbox\Error\Mapper $errorMapper,
        private \Schoolbox\Learning\Quiz\Mapper $quizMapper,
        private \Schoolbox\Learning\Quiz\Attempt\Mapper $attemptMapper,
        private StateMapper $stateMapper
    ) {
    }


    /**
     * Update the details of an attempt
     *
     * @param QuizDomainObject $assessment
     * @param User             $student
     * @param Model            $attempt
     * @return void
     */
    public function getModify(
        QuizDomainObject $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        /* TODO build attempt editor form here, which submits to postModify()
         */
    }


    /**
     * Update the details of an attempt by superimposing a POST array's values
     */
    public function postModify(QuizDomainObject $assessment, \Schoolbox\User $student): void
    {
        $postData = $this->requestPost->getArrayCopy();

        if ('' !== trim((string)$postData['responseId'])) {
            /* Load an existing Learnosity attempt and update its contents with the new
             * mark and overall commentary
             */
            $attempt = $this->attemptMapper->findById($postData['responseId']);
        } else {
            /* Find the most recent attempt/feedback and amend that, if it exists;
             * it sounds really weird, but it's because the feedback and the submission
             * are in the same table and model.
             */
            $response = $assessment->getParticipant($student)->findMostRecentSubmittedResponse();

            /* A Response model here isn't really the attempt model we're trying to manipulate;
             * we must fetch that corresponding instance separately. Swamp!
             *
             * If that exists, recycle it; otherwise spawn a new one altogether.
             */
            if (null !== $response) {
                $attempt = $this->attemptMapper->findById($response->getId());
            } else {
                /* If a feedback didn't already exist,
                 * create a whole new random attempt with some defaults
                 *
                 * TODO make defaults editable via Attempt Editor
                 */
                $quiz = $this->getQuizForAssessmentId($assessment->getId());

                $attempt = $this->attemptMapper->createModel()
                    ->setQuiz($quiz)
                    ->setUser($student)
                    ->setStart(\Alaress_Date_Format::now())
                    ->setFinish(\Alaress_Date_Format::now());
            }
        }

        /* Assign the maximum available marks based on what's potentially available
         */
        $maxMark = null;

        if ($assessment->getMarkScheme() instanceof TotalMarkFractional) {
            $maxMark = (float)($postData['maxMarks']);

            $assessment->getMarkScheme()->setTotalMark($maxMark);
        }

        /* If this is an actual grade, try to interpret the pieces:
         *     - Human-readable mark, from the combobox
         *     - Normalised mark derived from the human-readable mark
         */
        $mark = trim((string)$postData['mark']);

        /* Default values
         * Note that $mark might be empty string at this point:
         * this occurs when you add a comment before the grade, in the Quiz Quickmark screen
         */
        $normalisedMark = null;
        $humanReadableMark = null;

        /* Apparently this is how you detect a Submission State: an exception is thrown
         * trying to interpret it as one of the Mark Schemes. No wonder it was classified
         * as the $errors var. Wild!
         *
         * Shanked from Learning/Assessment/DueWork/Feedback/Factory.php::210
         */
        try {
            $normalisedMark = $assessment->getMarkScheme()->getNormalisedMark($mark);
            $humanReadableMark = $assessment->getMarkScheme()->getHumanReadableMark($normalisedMark);
        } catch (\InvalidArgumentException $e) {
            if ('' !== $mark) {
                /* If it's a Submission State, save the text as the human readable mark
                 * and derive its normalised mark from the uh Lists in admin
                 */
                $code = $this->stateMapper->findOneByColumn('display_as', $mark);

                $normalisedMark = $code->getNormaliseAs();
                $humanReadableMark = $mark;
            }
        }

        /* Update the deets and save it
         */
        $attempt->setComment(trim((string)$postData['comment']))
            ->setMark($humanReadableMark)
            ->setMaxMark($maxMark)
            ->setNormalisedMark($normalisedMark)
            ->setReviewer($this->getUser())
            ->setReviewed(\Alaress_Date_Format::now());

        /* TODO use setReviewed() as the publish mechanism
         */
        $this->attemptMapper->save($attempt);

        /* Async stuff for the frontend land
         */
        $data = [
            'success' => true,
            'message' => 'OK',
            'id'      => $attempt->getId(),
        ];

        $this->response = $this->responseDependentOnRequest('', $data)->withStatus(200);
    }


    /**
     * @param QuizDomainObject $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postRescore(
        QuizDomainObject $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        $attempt->setMark('cat');

        $this->attemptMapper->save($attempt);
    }


    /**
     * TODO Literally copypasta'd
     *
     * Converts an assessment_id to a Quiz
     *
     * @param int $assessmentId
     * @return Quiz
     * @throws Exception
     */
    protected function getQuizForAssessmentId(int $assessmentId): Quiz
    {
        /** @var Quiz $quiz */
        $quiz = $this->quizMapper->findByColumn('assessment_id', $assessmentId, ['deleted' => true])->first();

        if (Quiz::AVAILABILITY_ASSESS !== $quiz->getAvailability()) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        return $quiz;
    }
}
