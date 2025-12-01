<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Score;

use Alaress_Date_Format;
use OAT\Library\Lti1p3Ags\Model\Score\ScoreInterface;
use OutOfBoundsException;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\LTI\Assessment\LineItem\Model;
use Schoolbox\LTI\Assessment\Mapper as LtiAssessmentMapper;
use Schoolbox\LTI\Assessment\Model as LtiAssessmentModel;
use Schoolbox\LTI\Assessment\Score\Mapper as LtiScoreMapper;
use Schoolbox\LTI\Assessment\Score\Model as LtiScoreModel;
use Schoolbox\LTI\User\Codec as UserCodec;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Helper class for setting scores on LTI assessments.
 */
class Helper
{
    /**
     * The data model for `$this->ltiAssessment`.
     *
     * @var LtiAssessmentModel|null
     */
    private ?LtiAssessmentModel $ltiAssessmentModel = null;

    /**
     * Constructor
     *
     * @param LtiAssessment       $ltiAssessment       The LTI assessment to save scores for.
     *                                                 Note this is the domain object, not the data model.
     * @param LtiAssessmentMapper $ltiAssessmentMapper The mapper used to retrieve LTI assessments.
     * @param LtiScoreMapper      $ltiScoreMapper      The mapper used to save and retrieve LTI scores.
     * @param UserCodec           $userCodec           User codec, used to decode the user ID in the score into a user
     *                                                 to score.
     */
    public function __construct(
        private LtiAssessment $ltiAssessment,
        private LtiAssessmentMapper $ltiAssessmentMapper,
        private LtiScoreMapper $ltiScoreMapper,
        private UserCodec $userCodec,
    ) {
    }

    /**
     * Create a new score model from the LTI library's score object.
     *
     * @param ScoreInterface $score The LTI library's score object.
     * @return LtiScoreModel
     * @throws MapperException
     */
    public function createScoreModel(ScoreInterface $score): LtiScoreModel
    {
        /* Assuming LTI comments always get sent to us in plaintext, now that we render it
         * as rich text, convert this comment to HTML as if it were typed in a rich text editor...
         *
         * Converts any html entities to their unicode equivalents, as we still want
         * "Jana, make sure you place a <p> tag around the text" to show up correctly
         * and not be rendered as an actual <p> tag.
         */
        $comment = \Scrub::linkifyText(\Scrub::text($score->getComment()));

        /** For some reason linkifyText adds a new line at the end, so remove that
         *  Then convert any remaining \n to <br> tags, as we want to keep the line breaks
         *  and finally, surround it in an actual <p> tag so that it can be rendered as if ckeditor made it
         */
        $comment = nl2br(rtrim($comment, "\n"));
        $comment = "<p>$comment</p>";

        // The easy stuff
        // Interestingly the marking user is never set?
        $scoreModel = $this->ltiScoreMapper->createModel()
            ->setScoreGiven($score->getScoreGiven())
            ->setScoreMaximum($score->getScoreMaximum() ?? $this->ltiAssessment->getLineItemScoreMaximum())
            ->setComment($comment)
            ->setSubmittedAt(
                new Alaress_Date_Format($score->getTimestamp()->getTimestamp(), Alaress_Date_Format::TIMESTAMP)
            )
            ->setGradingProgress($score->getGradingProgressStatus())
            ->setActivityProgress($score->getActivityProgressStatus())
            ->setMarkingUser(null);

        // Response/feedback status: determine from grading/activity progress.
        return $scoreModel
            ->setIsResponse($scoreModel->determineResponseStatus())
            ->setIsFeedback($scoreModel->determineFeedbackStatus());
    }

    /**
     * Set the student from the LTI library's score object into Schoolbox's
     * score model.
     *
     * @param ScoreInterface $score      The LTI library's score object.
     * @param LtiScoreModel  $scoreModel The Schoolbox score model.
     * @return LtiScoreModel
     * @throws OutOfBoundsException if no user was found, or they are not a participant, or they are excluded
     */
    public function setStudentInScoreModel(ScoreInterface $score, LtiScoreModel $scoreModel): LtiScoreModel
    {
        // Student: check if they exist and are actually a participant
        $student = $this->userCodec->decode($score->getUserIdentifier());
        if (!$student) {
            throw new OutOfBoundsException(
                sprintf(
                    'No user with username "%s" exists',
                    $score->getUserIdentifier(),
                )
            );
        }
        $participant = $this->ltiAssessment->getParticipantIfExtant($student);
        if (!$participant) {
            throw new OutOfBoundsException(
                sprintf(
                    'User id "%d" is not a participant of assessment #%d',
                    $student->getId(),
                    $this->ltiAssessment->getId(),
                )
            );
        }
        if ($participant->isExcluded()) {
            throw new OutOfBoundsException(
                sprintf(
                    'User id "%d" has been excluded from assessment #%d',
                    $student->getId(),
                    $this->ltiAssessment->getId(),
                )
            );
        }
        return $scoreModel->setStudent($student);
    }

    /**
     * Set the LTI assessment into Schoolbox's score model.
     *
     * @param LtiScoreModel $scoreModel The Schoolbox score model.
     * @return LtiScoreModel
     * @throws OutOfBoundsException if no assessment was found
     */
    public function setLtiAssessmentInScoreModel(LtiScoreModel $scoreModel): LtiScoreModel
    {
        return $scoreModel->setLtiAssessment($this->findLtiAssessmentDataModel());
    }

    /**
     * Assert there has been no more recent score for this user for this
     * assessment.
     *
     * @param LtiScoreModel $scoreModel
     * @return void
     * @throws OutOfBoundsException if a more recent score exists for this student in this assessment
     */
    public function assertNoMoreRecentPreviousScore(LtiScoreModel $scoreModel, ?Model $ltiLineItem): void
    {
        // Find the most recent score on this assessment for the score's student
        // and (if provided) line item.
        $pb = $this->ltiScoreMapper->getPredicateBuilder()
            ->equals('lti_assessment_id', $this->findLtiAssessmentDataModel()->getId())
            ->equals('student_id', $scoreModel->getStudent()->getId());
        if ($ltiLineItem) {
            $pb = $pb->equals('lti_line_item_id', $ltiLineItem->getId());
        } else {
            $pb = $pb->isNull('lti_line_item_id');
        }
        /** @var LtiScoreModel|null $previousLtiScore */
        $previousLtiScore = $this->ltiScoreMapper->findByPredicates(
            $pb,
            [
                'order' => ['submitted_at' => 'desc'],
                'limit' => 1,
            ]
        )->first();

        // Don't save anything if the most recent previous score is more
        // recent than, or the same time as, the current score.
        if ($previousLtiScore && $previousLtiScore->getSubmittedAt()->gte($scoreModel->getSubmittedAt())) {
            throw new OutOfBoundsException(
                sprintf(
                    'User id "%s" has a more recent score for assessment #%d',
                    $scoreModel->getStudent()->getId(),
                    $this->ltiAssessment->getId(),
                )
            );
        }
    }

    /**
     * Find the data model for this helper's LTI assessment.
     *
     * @return LtiAssessmentModel
     * @throws OutOfBoundsException if no assessment was found
     */
    private function findLtiAssessmentDataModel(): object
    {
        if (!$this->ltiAssessmentModel) {
            try {
                $this->ltiAssessmentModel = $this->ltiAssessmentMapper->findOneByColumn(
                    'assessment_id',
                    $this->ltiAssessment->getId(),
                );
            } catch (MapperException $e) {
                throw new OutOfBoundsException(
                    sprintf('Assessment with ID #%d could not be found', $this->ltiAssessment->getId()),
                    0,
                    $e,
                );
            }
        }
        return $this->ltiAssessmentModel;
    }
}
