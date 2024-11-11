<?php

/**
 *
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\ImportStage;

use Alaress\Database\Connector\IConnector;
use Alaress\Html\ToText as Html2Text;
use External\Canvas\Importer\CanvasDb;
use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Alaress\Database;
use Alaress\Database\Predicate\Builder;
use External\Canvas\Sync\ImportStage\Traits\ModelSaveTrait;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Assessment\Participant;
use Schoolbox\Mapper;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\Quiz;
use Schoolbox\Learning\Quiz\Section as QuizSection;
use Schoolbox\Learning\Quiz\Section\Question as QuizQuestion;

use function implode;

/**
 * Responsible for importing Quiz Submissions.
 */
class QuizSubmission extends SubmissionAbstract
{
    use ModelSaveTrait;

    /**
     * @param CanvasDb                 $canvasDb
     * @param IConnector                 $schoolboxDb
     * @param Quiz\Mapper                $quizMapper
     * @param QuizSection\Mapper         $quizSectionMapper
     * @param QuizQuestion\Mapper        $quizQuestionMapper
     * @param Quiz\Attempt\Mapper        $quizAttemptMapper
     * @param Quiz\Attempt\Answer\Mapper $quizAnswerMapper
     * @param Participant\Mapper         $participantMapper
     * @param UserMapper                 $userMapper
     * @param LoggerInterface            $log
     * @param Assessment\MapperProxy     $assessmentDataSource
     * @param string                     $schoolboxTimezone
     */
    public function __construct(
        CanvasDb $canvasDb,
        IConnector $schoolboxDb,
        private Quiz\Mapper $quizMapper,
        private QuizSection\Mapper $quizSectionMapper,
        private QuizQuestion\Mapper $quizQuestionMapper,
        private Quiz\Attempt\Mapper $quizAttemptMapper,
        private Quiz\Attempt\Answer\Mapper $quizAnswerMapper,
        Participant\Mapper $participantMapper,
        UserMapper $userMapper,
        LoggerInterface $log,
        Assessment\MapperProxy $assessmentDataSource,
        private string $schoolboxTimezone
    ) {
        parent::__construct(
            $canvasDb,
            $schoolboxDb,
            $participantMapper,
            $userMapper,
            $log,
            $assessmentDataSource
        );
    }


    /**
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     */
    public function sync($canvasAssessment, $assessmentCanvasId): void
    {
        $markSchemaFactory = new MarkSchemeFactory();

        $hasMarkToSync = false;
        $syncMarkCount = 0;
        $sbAssessSpec  = $this->quizMapper->findOneByColumn('external_id', $assessmentCanvasId);

        $assessmentImpls   = $this->findAllImplementationsBySpecificationId(
            $sbAssessSpec->getAssessmentModel()
                         ->getId()
        );
        $canvasSubmissions = $this->loadCanvasSubmissions($assessmentCanvasId);
        $this->log->info("Processing submissions for Canvas Assignment '{$canvasAssessment['title']}' (CanvasId: $assessmentCanvasId)");

        /** @var IAssessable $assessmentImpl */
        foreach ($assessmentImpls as $assessmentImpl) {
            // load participants
            $assessmentImpl = $this->loadParticipants($assessmentImpl);
            if (!$assessmentImpl instanceof IAssessable) {
                continue;
            }
            $posted = false;

            // get existing marks for this assessment
            $quiz     = $this->quizMapper->findOneByColumn('assessment_id', $assessmentImpl->getId());
            $sections = $this->quizSectionMapper->findByColumn('assess_id', $quiz->getId());
            foreach ($sections as $key => $section) {
                $questions = $this->quizQuestionMapper->findByColumn('section_id', $section->getId());
                $sections->offsetSet($key, $section->setQuestions($questions));
            }
            $quiz->setSections($sections);

            $attempts = $this->quizAttemptMapper->findByColumn('assess_id', $quiz->getId());


            $syncedFeedbackIds = [];

            $markScheme = $markSchemaFactory->spawnMarkSchemeFor($assessmentImpl);

            foreach ($assessmentImpl->getPlans() as $plan) {
                foreach ($plan->getParticipants() as $participant) {
                    foreach ($canvasSubmissions as $canvasSubmission) {
                        if ($canvasSubmission['reviewee_id'] !== $participant->getUser()->getExternalId()) {
                            // current canvas submission is not for this participant
                            continue;
                        }

                        // $participant should be a school box user with this reviewee_id as their external_id
                        $reviewee = $participant->getUser();
                        if (
                            !empty(
                                $canvasSubmission['comment']
                                && $canvasSubmission['comment_created_at'] > $canvasSubmission['graded_at']
                            )
                        ) {
                            $reviewer     = $this->findUserByCanvasUserId((int)$canvasSubmission['commenter_id']);
                            $feedbackTime = $canvasSubmission['comment_created_at'];
                        } else {
                            $reviewer     = $this->findUserByCanvasUserId((int)$canvasSubmission['grader_user_id']);
                            $feedbackTime = $canvasSubmission['graded_at'];
                        }
                        $this->saveParticipant(
                            $plan,
                            $participant,
                            $this->convertTime($feedbackTime),
                            $canvasSubmission
                        );

                        $feedback = $this->syncFeedback(
                            $quiz,
                            $reviewer,
                            $reviewee,
                            $canvasSubmission,
                            $attempts,
                            $markScheme,
                            $feedbackTime
                        );
                        if (!$feedback) {
                            continue;
                        }
                        if ($canvasSubmission['posted_at']) {
                            $posted = true;
                        }
                        $syncedFeedbackIds[] = $feedback->getId();
                        $syncMarkCount++;
                        $hasMarkToSync = true;
                    }
                }
            }
            if (!$posted) {
                $this->log->debug("Changed to private");
                $assessableDataModel = $this->assessmentDataSource->translateToDataModel($assessmentImpl);
                $quiz = $assessableDataModel->getQuiz();
                $quiz->setMarkVisibility(IAssessable::MARK_VISIBILITY_PRIVATE);
                $this->saveModel($this->quizMapper, $quiz, false, false);
            }
            $this->deleteUnSynchronisedFeedbacks($attempts, $syncedFeedbackIds);
        }
        if (!$hasMarkToSync) {
            $this->log->debug("This Canvas assignment has no marks to sync");
        }

        $this->log->debug("{$syncMarkCount} marks processed.");
    }


    /**
     * Finds or creates Schoolbox Feedback corresponding to the given canvas submission
     *
     * @param IAssessableDataModel $iAssessable
     * @param User                 $reviewer
     * @param User                 $reviewee
     * @param array                $canvasSubmission
     * @param GenericCollection    $existingFeedbacks
     * @param IMarkScheme          $markScheme
     * @param string               $feedbackTime
     * @return Quiz\Attempt\Model|null
     * @throws Mapper\Exception
     */
    public function syncFeedback(
        IAssessableDataModel $iAssessable,
        User $reviewer,
        User $reviewee,
        array $canvasSubmission,
        GenericCollection $existingFeedbacks,
        IMarkScheme $markScheme,
        string $feedbackTime
    ): ?Quiz\Attempt\Model {
        /** @var Quiz\Model $quiz */
        $quiz = $iAssessable;
        $this->log->debug(sprintf('Processing marks for quiz: assess_id:%s', $quiz->getId()));
        $mark = $canvasSubmission['mark']; // submissions.published_grade as mark this should be safe to use as a string|null so just add that in
        if (is_numeric($canvasSubmission['norm_mark'])) {
            $normMark = (float)$canvasSubmission['norm_mark'];
        } else {
            $this->log->debug(sprintf('Converting norm mark: [%s] to null for %s', (string)$canvasSubmission['norm_mark'], $quiz->getId()));
            $normMark = null;
        }
        if (is_numeric($canvasSubmission['max_mark'])) {
            $maxMark = (float)$canvasSubmission['max_mark'];
        } else {
            $this->log->debug(sprintf('Converting max mark: [%s] to null for %s', (string)$canvasSubmission['max_mark'], $quiz->getId()));
            $maxMark = null;
        }

        $responses = $this->quizAnswerMapper->findByColumn('attempt_id', $existingFeedbacks->getPropertyValues('id'));
        // should there be one response to one attempt
        $responses = $responses->indexByCallback(static function (Quiz\Attempt\Answer\Model $res) {
            return $res->getAttempt()->getId();
        });

        /** @var Quiz\Attempt\Model $attempt */
        foreach ($existingFeedbacks as $attempt) {
            /** @var Quiz\Attempt\Answer\Model|null $response */
            $response = $responses->offsetGet($attempt->getId());
            // Check whether the rest are the same
            // Floating point precision in Schoolbox's getMark() is less than in canvas, so an exact match won't always work.
            // We have to test equality somewhat approximately, but with reasonable certainty.
            if (
                $response
                && $attempt->getUser()->getId() === $reviewee->getId()
                && $attempt->getReviewer()->getId() === $reviewer->getId()
                && (abs($response->getMark() - (float)$normMark) < 0.0001)
                && (string)$response->getComment() === (string)$canvasSubmission['comment']
            ) {
                $this->log->debug(sprintf('Feedback already exists for attempt: [%s]', $attempt->getId()));
                return $attempt;
            }
        }

        // time string from Canvas is coming in UTC
        $feedbackTime = $this->convertTime($feedbackTime);
        $startTime    = $this->convertTime((string)$canvasSubmission['started_at']);
        $finishTime   = $this->convertTime((string)$canvasSubmission['finished_at']);

        /** @var QuizSection\Model $section */
        $section = $quiz->getSections()->first();
        /** @var QuizQuestion\Model $question */
        $question = $section->getQuestions()?->first();
        $question->setSection($section);

        // convert to plaintext first as a precaution
        $plainComment = new Html2Text($canvasSubmission['comment']);

        $answers = $this->quizAnswerMapper->createCollection();
        $answer  = $this->quizAnswerMapper->createModel()
                                          ->setMark($normMark)
                                          ->setComment($plainComment->getText())
                                          ->setAutoMarked(false)
                                          ->setQuestion($question)
                                          ->setUpdatedAt($feedbackTime);
        // Couldn't find. Creating a new feedback
        $attempt = $this->quizAttemptMapper->createModel()
                                           ->setQuiz($quiz)
                                           ->setReviewer($reviewer)
                                           ->setUser($reviewee)
                                           ->setMark($mark)
                                           ->setNormalisedMark($normMark)
                                           ->setMaxMark($maxMark)
                                           ->setAnswers($answers)
                                           ->setReviewed($feedbackTime)
                                           ->setStart($startTime)
                                           ->setFinish($finishTime);


        /* Cannot use mappers here to save because:
         * 1. We want to preserve Canvas create/update timestamps (alt. method would be to save via mapper, then raw query to override)
         * 2. Too many inappropriate pre/post save callbacks on mappers which cannot be altered in a master feature.
         */
        try {
            $attempt->getAnswers()->add($answer);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
            return null;
        }
        try {
            $attempt = $this->saveModel($this->quizAttemptMapper, $attempt, false, false);
            $answer->setAttempt($attempt);
            $answer = $this->saveModel($this->quizAnswerMapper, $answer, false, false);
            $this->log->debug(sprintf('Attempt saved: [%s]', $attempt->getId()));
            return $attempt;
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
            return null;
        }
    }


    /**
     * Deletes existing feedbacks which did not get synced
     *
     * @param GenericCollection $existingFeedbacks
     * @param array             $syncedFeedbackIds
     */
    private function deleteUnSynchronisedFeedbacks(GenericCollection $existingFeedbacks, array $syncedFeedbackIds)
    {
        $deleteIds = array_diff($existingFeedbacks->getPropertyValues('id'), $syncedFeedbackIds);
        $this->quizAttemptMapper->deleteByColumn('assess_id', $deleteIds);
    }


    /**
     * Fetch canvas submissions (include marks) related to the given assignment
     *
     * @param int $assignmentCanvasId
     * @return array
     */
    private function loadCanvasSubmissions(int $assignmentCanvasId): array
    {
        $mySqlQuoter = new Database\Quoter\Mysql\Quoter();
        $pb          = (new Builder($mySqlQuoter, ''))->equals('quizzes.id', $assignmentCanvasId);
        $pb->equals('submissions.workflow_state', 'graded');
        $whereClause = implode(' AND ', $pb->getWhere());

        $sql = "
            SELECT
                assignments.id as canvas_id,
                submissions.id as submission_id,
                submissions.published_grade as mark,
                submissions.excused as excused,
                submissions.posted_at as posted_at,
                quiz_submissions.score as norm_mark,
                quiz_submissions.quiz_points_possible as max_mark,
                CONVERT_TZ(quiz_submissions.started_at, 'UTC', :timezone) as started_at,
                CONVERT_TZ(quiz_submissions.finished_at, 'UTC', :timezone) as finished_at,
                reviewee.sis_user_id as reviewee_id,
                submissions.grader_id as grader_user_id,
                CONVERT_TZ(submissions.graded_at, 'UTC', :timezone) as graded_at,
                submission_comments.comment as comment,
                submission_comments.author_id as commenter_id,
                CONVERT_TZ(submission_comments.created_at, 'UTC', :timezone) as comment_created_at,
                CONVERT_TZ(submission_comments.updated_at, 'UTC', :timezone) as comment_updated_at
            FROM assignments
            JOIN quizzes ON quizzes.assignment_id = assignments.id
            LEFT JOIN submissions ON assignments.id = submissions.assignment_id
            LEFT JOIN quiz_submissions ON quiz_submissions.id = submissions.quiz_submission_id
            LEFT JOIN submission_comments ON submission_comments.submission_id = submissions.id
                                          AND submission_comments.author_id <> submissions.user_id
            LEFT JOIN pseudonyms as reviewee ON reviewee.user_id = submissions.user_id AND reviewee.workflow_state = 'active'

            WHERE {$whereClause}
            ORDER BY submissions.id
        ";

        if (!$this->canvasDb->isCanvasData2) {
            $pb          = (new Builder($mySqlQuoter, ''))->equals('quiz_dim.canvas_id', $assignmentCanvasId);
            $pb->equals('submission_dim.workflow_state', 'graded');
            $whereClause = implode(' AND ', $pb->getWhere());
            $sql = "
            SELECT
                assignment_dim.id,
                assignment_dim.canvas_id,
                submission_dim.id as submission_id,
                submission_dim.published_grade as mark,
                submission_dim.excused as excused,
                submission_dim.posted_at as posted_at,
                quiz_submission_fact.score as norm_mark,
                quiz_submission_fact.quiz_points_possible as max_mark,
                CONVERT_TZ(quiz_submission_dim.started_at, 'UTC', :timezone) as started_at,
                CONVERT_TZ(quiz_submission_dim.finished_at, 'UTC', :timezone) as finished_at,
                reviewee.sis_user_id as reviewee_id,
                submission_dim.grader_id as grader_user_id,
                CONVERT_TZ(submission_dim.graded_at, 'UTC', :timezone) as graded_at,
                submission_comment_dim.comment as comment,
                submission_comment_dim.author_id as commenter_id,
                CONVERT_TZ(submission_comment_dim.created_at, 'UTC', :timezone) as comment_created_at,
                CONVERT_TZ(submission_comment_dim.updated_at, 'UTC', :timezone) as comment_updated_at
            FROM assignment_dim
            JOIN quiz_dim ON quiz_dim.assignment_id = assignment_dim.id
            LEFT JOIN submission_dim ON assignment_dim.id = submission_dim.assignment_id
            LEFT JOIN quiz_submission_dim ON quiz_submission_dim.id = submission_dim.quiz_submission_id
            LEFT JOIN quiz_submission_fact ON quiz_submission_fact.quiz_submission_id = quiz_submission_dim.id
            LEFT JOIN submission_comment_dim ON submission_comment_dim.submission_id = submission_dim.id
                                             AND submission_comment_dim.author_id <> submission_dim.user_id
            LEFT JOIN pseudonym_dim as reviewee ON reviewee.user_id = submission_dim.user_id AND reviewee.workflow_state = 'active'

            WHERE {$whereClause}
            ORDER BY submission_dim.id
        ";
        }

        $statement = $this->canvasDb->getConnector()->executeQuery($sql, array_merge($pb->getParams(), ['timezone' => $this->schoolboxTimezone]));
        return $statement->fetchAll();
    }
}
