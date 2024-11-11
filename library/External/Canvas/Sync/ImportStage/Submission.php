<?php

/**
 *
 * @copyright   Copyright 2020, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\ImportStage;

use Alaress\Database\Connector\IConnector;
use Alaress\Html\ToText as Html2Text;
use External\Canvas\Importer\CanvasDb;
use External\Canvas\Sync\Bridge\Assignment;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Alaress\Database;
use Alaress\Database\Predicate\Builder;
use External\Canvas\Sync\ImportStage\Traits\ModelSaveTrait;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Assessment\Participant;
use Schoolbox\Mapper;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;

use function implode;

/**
 * Responsible for importing Submissions.
 */
class Submission extends SubmissionAbstract
{
    use ModelSaveTrait;

    /**
     * @param CanvasDb            $canvasDb
     * @param IConnector          $schoolboxDb
     * @param SubmissionBoxMapper $submissionBoxMapper
     * @param ReturnedWorkMapper  $returnedWorkMapper
     * @param Participant\Mapper  $participantMapper
     * @param UserMapper          $userMapper
     * @param LoggerInterface     $log
     * @param MapperProxy         $assessmentDataSource
     * @param string              $schoolboxTimezone
     */
    public function __construct(
        CanvasDb $canvasDb,
        IConnector $schoolboxDb,
        private SubmissionBoxMapper $submissionBoxMapper,
        private ReturnedWorkMapper $returnedWorkMapper,
        Participant\Mapper $participantMapper,
        UserMapper $userMapper,
        LoggerInterface $log,
        Assessment\MapperProxy $assessmentDataSource,
        private string $schoolboxTimezone,
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

        $hasMarkToSync       = false;
        $syncMarkCount       = 0;
        $sbSubmissionBoxSpec = $this->submissionBoxMapper->findOneByColumn('external_id', $assessmentCanvasId);

        $assessmentImpls   = $this->findAllImplementationsBySpecificationId(
            $sbSubmissionBoxSpec->getAssessmentModel()->getId()
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
            $submissionBox     = $this->submissionBoxMapper->findOneByColumn('assessment_id', $assessmentImpl->getId());
            $existingFeedbacks = $this->returnedWorkMapper->findByColumnList(['box_id' => $submissionBox->getId()]);
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
                        // save participant
                        $this->saveParticipant(
                            $plan,
                            $participant,
                            $this->convertTime($feedbackTime),
                            $canvasSubmission
                        );

                        $feedback = $this->syncFeedback(
                            $submissionBox,
                            $reviewer,
                            $reviewee,
                            $canvasSubmission,
                            $existingFeedbacks,
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
                $dueWork = $assessableDataModel->getDueWork();
                $dueWork->setMarkVisibility(IAssessable::MARK_VISIBILITY_PRIVATE);
                $this->saveModel($this->submissionBoxMapper, $dueWork, false, false);
            }
            $this->deleteUnSynchronisedFeedbacks($existingFeedbacks, $syncedFeedbackIds);
        }
        if (!$hasMarkToSync) {
            $this->log->debug("This Canvas assignment has no marks to sync");
        }

        $this->log->debug("{$syncMarkCount} marks processed.");
    }


    /**
     * Finds or creates Schoolbox Feedback corresponding to the given canvas submission
     *
     * @param Assessment\IAssessableDataModel $iAssessable
     * @param User                            $reviewer
     * @param User                            $reviewee
     * @param array                           $canvasSubmission
     * @param GenericCollection               $existingFeedbacks
     * @param IMarkScheme                     $markScheme
     * @param string                          $feedbackTime
     * @return SubmissionBox\ReturnedWork|null
     * @throws Mapper\Exception
     */
    public function syncFeedback(
        Assessment\IAssessableDataModel $iAssessable,
        User $reviewer,
        User $reviewee,
        array $canvasSubmission,
        GenericCollection $existingFeedbacks,
        IMarkScheme $markScheme,
        string $feedbackTime
    ): ?SubmissionBox\ReturnedWork {
        /** @var SubmissionBox $submissionBox */
        $submissionBox = $iAssessable;
        $this->log->debug(sprintf('Processing marks for submission_box: box_id:%s student:%d', $submissionBox->getId(), $reviewee->getId()));
        $normMark     = $canvasSubmission['norm_mark'];
        $readableMark = $canvasSubmission['mark'];

        if (
            $submissionBox->getMarkType()->getSlug() === SubmissionBox\MarkType::SLUG_LETTER
            && in_array($readableMark, [Assignment::MARK_COMPLETE, Assignment::MARK_INCOMPLETE,])
        ) {
            // there are complete marks with 0 points, which confuses SB
            $normMark = $readableMark === Assignment::MARK_COMPLETE ? 100 : 0;
        } elseif ($normMark !== null && $submissionBox->getMaxMark()) {
            $normMark = ($normMark / $submissionBox->getMaxMark()) * 100;
        } elseif ($readableMark !== null && $submissionBox->getMarkType()->getSlug() !== SubmissionBox\MarkType::SLUG_NONE) {
            // If submission_dim.published_grade exists but submission_fact.published_score doesn't,
            // try to get norm_mark from published_grade
            try {
                $normMark = $markScheme->getNormalisedMark($readableMark);
            } catch (\Exception $e) {
                $this->log->debug(sprintf('Could not normalise mark %s for student:%d :%s', $readableMark, $reviewee->getId(), $e->getMessage()));
            }
        }

        if ($submissionBox->getMarkType()->getSlug() !== SubmissionBox\MarkType::SLUG_NONE) {
            $readableMark = $markScheme->getHumanReadableMark($normMark);
        }

        /** @var SubmissionBox\ReturnedWork $existingFeedback */
        foreach ($existingFeedbacks as $existingFeedback) {
            // Check whether the rest are the same
            // Floating point precision in Schoolbox's getMark() is less than in canvas, so an exact match won't always work.
            // We have to test equality somewhat approximately, but with reasonable certainty.
            if (
                $existingFeedback->getOwner()->getId() === $reviewee->getId()
                && $existingFeedback->getMarker()->getId() === $reviewer->getId()
                && (string)$existingFeedback->getComment() === (string)$canvasSubmission['comment']
                && (string)$existingFeedback->getHumanReadableMark() === (string)$readableMark
                && (abs((float)$existingFeedback->getNormalizedMark() - (float)$normMark) < 0.0001)
            ) {
                $this->log->debug(sprintf('Feedback already exists : submission_return_id:%s', $existingFeedback->getId()));
                return $existingFeedback;
            }
        }

        // time string from Canvas is coming in UTC
        $feedbackTime = $this->convertTime($feedbackTime);

        // convert to plaintext first as a precaution
        $plainComment = new Html2Text($canvasSubmission['comment']);

        // Couldn't find. Creating a new feedback
        $feedback = $this->returnedWorkMapper->createModel();
        $feedback->setSubmissionBox($submissionBox)
                 ->setMarker($reviewer)
                 ->setOwner($reviewee)
                 ->setReviewRoute(SubmissionBox\ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
                 ->setComment($plainComment->getText())
                 ->setHumanReadableMark($readableMark)
                 ->setNormalizedMark($normMark)
                 ->setCreatedAt($feedbackTime)
                 ->setPublishedAt($feedbackTime)
                 ->setPublishedBy($reviewer->getId())
                 ->setUpdatedAt($feedbackTime);

        /* Cannot use mappers here to save because:
         * 1. We want to preserve Canvas create/update timestamps (alt. method would be to save via mapper, then raw query to override)
         * 2. Too many inappropriate pre/post save callbacks on mappers which cannot be altered in a master feature.
         */
        try {
            $feedback = $this->saveModel($this->returnedWorkMapper, $feedback, false, false);
            $this->log->debug(sprintf('Submission return saved: [%s]', $feedback->getId()));
            return $feedback;
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
        $this->returnedWorkMapper->deleteByColumn('id', $deleteIds);
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

        $pb          = (new Builder($mySqlQuoter, ''))->equals('assignments.id', $assignmentCanvasId);
        $pb->equals('submissions.workflow_state', 'graded');
        $whereClause = implode(' AND ', $pb->getWhere());

        $sql = "
            SELECT
                assignments.id,
                assignments.id as canvas_id,
                submissions.id as submission_id,
                submissions.published_grade as mark,
                submissions.excused as excused,
                submissions.posted_at as posted_at,
                submissions.published_score as norm_mark,
                reviewee.sis_user_id as reviewee_id,
                submissions.grader_id as grader_user_id,
                CONVERT_TZ(submissions.graded_at, 'UTC', :timezone) as graded_at,
                submission_comments.comment as comment,
                submission_comments.author_id as commenter_id,
                CONVERT_TZ(submission_comments.created_at, 'UTC', :timezone) as comment_created_at,
                CONVERT_TZ(submission_comments.updated_at, 'UTC', :timezone) as comment_updated_at
            FROM assignments
            LEFT JOIN submissions ON assignments.id = submissions.assignment_id
            LEFT JOIN submission_comments ON submission_comments.submission_id = submissions.id
                                             AND submission_comments.author_id <> submissions.user_id
            LEFT JOIN pseudonyms as reviewee ON reviewee.user_id = submissions.user_id AND reviewee.workflow_state = 'active'

            WHERE {$whereClause}
            ORDER BY submissions.id
        ";

        if (!$this->canvasDb->isCanvasData2) {
            $pb          = (new Builder($mySqlQuoter, ''))->equals('assignment_dim.canvas_id', $assignmentCanvasId);
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
                submission_fact.published_score as norm_mark,
                reviewee.sis_user_id as reviewee_id,
                submission_dim.grader_id as grader_user_id,
                CONVERT_TZ(submission_dim.graded_at, 'UTC', :timezone) as graded_at,
                submission_comment_dim.comment as comment,
                submission_comment_dim.author_id as commenter_id,
                CONVERT_TZ(submission_comment_dim.created_at, 'UTC', :timezone) as comment_created_at,
                CONVERT_TZ(submission_comment_dim.updated_at, 'UTC', :timezone) as comment_updated_at
            FROM assignment_dim
            LEFT JOIN submission_dim ON assignment_dim.id = submission_dim.assignment_id
            LEFT JOIN submission_fact ON submission_fact.submission_id = submission_dim.id
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
