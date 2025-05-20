<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Score;

use Alaress_Date_Format;
use OAT\Library\Lti1p3Ags\Model\Score\ScoreInterface;
use OAT\Library\Lti1p3Ags\Repository\ScoreRepositoryInterface;
use OutOfBoundsException;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\LTI\Assessment\Mapper as LtiAssessmentMapper;
use Schoolbox\LTI\Assessment\Score\Mapper as LtiScoreMapper;
use Schoolbox\LTI\Assessment\Score\Model as LtiScoreModel;
use Schoolbox\LTI\User\Codec as UserCodec;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * A score repository for a single LTI assessment.
 */
class SingleAssessmentScoreRepository implements ScoreRepositoryInterface
{
    /**
     * The LTI assessment to save scores for.
     */
    private LtiAssessment $ltiAssessment;

    /**
     * The mapper used to retrieve LTI assessments.
     */
    private LtiAssessmentMapper $ltiAssessmentMapper;

    /**
     * The mapper used to save and retrieve LTI scores.
     */
    private LtiScoreMapper $ltiScoreMapper;

    /**
     * User codec, used to decode the user ID in the score into a user to score.
     */
    private UserCodec $userCodec;

    /**
     * Constructor
     *
     * @param LtiAssessment       $model
     * @param LtiAssessmentMapper $ltiAssessmentMapper
     * @param LtiScoreMapper      $scoreMapper
     * @param UserCodec           $userCodec
     */
    public function __construct(
        LtiAssessment $model,
        LtiAssessmentMapper $ltiAssessmentMapper,
        LtiScoreMapper $scoreMapper,
        UserCodec $userCodec
    ) {
        $this->ltiAssessment       = $model;
        $this->ltiAssessmentMapper = $ltiAssessmentMapper;
        $this->ltiScoreMapper      = $scoreMapper;
        $this->userCodec           = $userCodec;
    }

    /**
     * Save the score for this repository's assessment.
     *
     * @param ScoreInterface $score
     * @return ScoreInterface
     * @throws MapperException if for some reason the score model couldn't be created
     */
    public function save(ScoreInterface $score): ScoreInterface
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
        $ltiScore = $this->ltiScoreMapper->createModel()
            ->setScoreGiven($score->getScoreGiven())
            ->setScoreMaximum($score->getScoreMaximum() ?? $this->ltiAssessment->getLineItemScoreMaximum())
            ->setComment($comment)
            ->setSubmittedAt(
                new Alaress_Date_Format($score->getTimestamp()->getTimestamp(), Alaress_Date_Format::TIMESTAMP)
            )
            ->setGradingProgress($score->getGradingProgressStatus())
            ->setActivityProgress($score->getActivityProgressStatus())
            ->setMarkingUser(null);

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
        $ltiScore = $ltiScore->setStudent($student);

        // Assessment: load from mapper
        try {
            $ltiAssessmentDataModel = $this->ltiAssessmentMapper->findOneByColumn('assessment_id', $this->ltiAssessment->getId());
        } catch (MapperException $e) {
            throw new OutOfBoundsException(
                sprintf('Assessment with ID #%d could not be found', $this->ltiAssessment->getId())
            );
        }
        $ltiScore = $ltiScore->setLtiAssessment($ltiAssessmentDataModel);

        // Find the most recent previous score
        /** @var LtiScoreModel|null $previousLtiScore */
        $previousLtiScore = $this->ltiScoreMapper->findByColumnList(
            [
                'lti_assessment_id' => $ltiAssessmentDataModel->getId(),
                'student_id'        => $student->getId(),
            ],
            [
                'order' => ['submitted_at' => 'desc'],
                'limit' => 1,
            ]
        )->first();

        // Don't save anything if the most recent previous score is more
        // recent than, or the same time as, the current score.
        if ($previousLtiScore && $previousLtiScore->getSubmittedAt()->gte($ltiScore->getSubmittedAt())) {
            throw new OutOfBoundsException(
                sprintf(
                    'User id "%s" has a more recent score for assessment #%d',
                    $student->getId(),
                    $this->ltiAssessment->getId(),
                )
            );
        }

        // Response/feedback status: determine from grading/activity progress,
        // and previous score.
        $ltiScore = $ltiScore
            ->setIsResponse($ltiScore->determineResponseStatus())
            ->setIsFeedback($ltiScore->determineFeedbackStatus());

        $this->ltiScoreMapper->save($ltiScore);
        return $score;
    }
}
