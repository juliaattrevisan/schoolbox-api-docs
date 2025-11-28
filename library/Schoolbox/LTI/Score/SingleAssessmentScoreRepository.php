<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Score;

use OAT\Library\Lti1p3Ags\Model\Score\ScoreInterface;
use OAT\Library\Lti1p3Ags\Repository\ScoreRepositoryInterface;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\LTI\Assessment\Mapper as LtiAssessmentMapper;
use Schoolbox\LTI\Assessment\Score\Mapper as LtiScoreMapper;
use Schoolbox\LTI\User\Codec as UserCodec;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * A score repository for a single LTI assessment.
 */
class SingleAssessmentScoreRepository implements ScoreRepositoryInterface
{
    /**
     * Constructor
     *
     * @param LtiAssessment       $ltiAssessment       The LTI assessment to save scores for.
     *                                                 Note this is the domain object, not the data model.
     * @param LtiAssessmentMapper $ltiAssessmentMapper The mapper used to retrieve LTI assessments.
     * @param LtiScoreMapper      $ltiScoreMapper      The mapper used to save and retrieve LTI scores.
     * @param UserCodec           $userCodec           User codec, used to decode the user ID in the score into a user
     *                                                 to score.
     * @param LoggerInterface     $logger              Logger for what this repository does.
     */
    public function __construct(
        private LtiAssessment $ltiAssessment,
        private LtiAssessmentMapper $ltiAssessmentMapper,
        private LtiScoreMapper $ltiScoreMapper,
        private UserCodec $userCodec,
        private LoggerInterface $logger,
    ) {
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
        $scoreHelper = new Helper(
            $this->ltiAssessment,
            $this->ltiAssessmentMapper,
            $this->ltiScoreMapper,
            $this->userCodec,
        );
        $scoreModel = $scoreHelper->createScoreModel($score);
        try {
            $scoreModel = $scoreHelper->setStudentInScoreModel($score, $scoreModel);
            $scoreModel = $scoreHelper->setLtiAssessmentInScoreModel($scoreModel);
            $scoreHelper->assertNoMoreRecentPreviousScore($scoreModel, null);
        } catch (OutOfBoundsException $e) {
            $this->logger->error(
                __METHOD__ . ': set error',
                ['message' => $e->getMessage()],
            );
            throw $e;
        }
        try {
            $this->ltiScoreMapper->save($scoreModel);
        } catch (MapperException $e) {
            $this->logger->error(
                __METHOD__ . ': save error',
                ['message' => $e->getMessage()],
            );
            throw $e;
        }
        return $score;
    }
}
