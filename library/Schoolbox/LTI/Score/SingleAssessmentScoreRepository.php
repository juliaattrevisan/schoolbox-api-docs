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
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

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
     * User mapper, for retrieving the student to score.
     */
    private UserMapper $userMapper;

    /**
     * Constructor
     *
     * @param LtiAssessment       $model
     * @param LtiAssessmentMapper $ltiAssessmentMapper
     * @param LtiScoreMapper      $scoreMapper
     * @param UserMapper          $userMapper
     */
    public function __construct(
        LtiAssessment $model,
        LtiAssessmentMapper $ltiAssessmentMapper,
        LtiScoreMapper $scoreMapper,
        UserMapper $userMapper
    ) {
        $this->ltiAssessment       = $model;
        $this->ltiAssessmentMapper = $ltiAssessmentMapper;
        $this->ltiScoreMapper      = $scoreMapper;
        $this->userMapper          = $userMapper;
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
        // The easy stuff
        $ltiScore = $this->ltiScoreMapper->createModel()
            ->setScoreGiven($score->getScoreGiven())
            ->setScoreMaximum($score->getScoreMaximum() ?? $this->ltiAssessment->getLineItemScoreMaximum())
            ->setComment($score->getComment())
            ->setSubmittedAt(
                new Alaress_Date_Format($score->getTimestamp()->getTimestamp(), Alaress_Date_Format::TIMESTAMP)
            )
            ->setGradingProgress($score->getGradingProgressStatus())
            ->setActivityProgress($score->getActivityProgressStatus())
            ->setMarkingUser(null);

        // Student: check if they exist and are actually a participant
        $student = $this->userMapper->findByUsername($score->getUserIdentifier());
        if (!$student) {
            throw new OutOfBoundsException(sprintf('No user with username "%s" exists', $score->getUserIdentifier()));
        }
        $participant = $this->ltiAssessment->getParticipantIfExtant($student);
        if (!$participant) {
            throw new OutOfBoundsException(
                sprintf('User with username "%s" is not a participant of assessment', $student->getUsername())
            );
        }
        if ($participant->isExcluded()) {
            throw new OutOfBoundsException(
                sprintf('User with username "%s" has been excluded from assessment', $student->getUsername())
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

        // Response/feedback status: determine from grading/activity progress,
        // and previous score
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
        $ltiScore = $ltiScore
            ->setIsResponse($ltiScore->determineResponseStatus($previousLtiScore))
            ->setIsFeedback($ltiScore->determineFeedbackStatus($previousLtiScore));

        $this->ltiScoreMapper->save($ltiScore);
        return $score;
    }
}
