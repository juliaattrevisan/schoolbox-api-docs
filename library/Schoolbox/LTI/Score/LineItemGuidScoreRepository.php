<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Score;

use OAT\Library\Lti1p3Ags\Model\Score\ScoreInterface;
use OAT\Library\Lti1p3Ags\Repository\ScoreRepositoryInterface;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\LTI\Assessment;
use Schoolbox\LTI\User\Codec as UserCodec;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Score repository for line items.
 *
 * Works just like `SingleAssessmentScoreRepository`, but also:
 * * associates the score with the line item as well as its assessment
 * * asserts the line item matches the assessment
 * * modifies the previous score assertion: only the line item is checked
 */
class LineItemGuidScoreRepository implements ScoreRepositoryInterface
{
    /**
     * Constructor
     *
     * @param LtiAssessment             $ltiAssessment       The LTI assessment to save scores for.
     *                                                       Note this is the domain object, not the data model.
     * @param Assessment\LineItem\Model $ltiLineItem         The line item to save the score for.
     * @param Assessment\Mapper         $ltiAssessmentMapper The mapper used to retrieve LTI assessments.
     * @param Assessment\Score\Mapper   $ltiScoreMapper      The mapper used to save and retrieve LTI scores.
     * @param UserCodec                 $userCodec           User codec, used to decode the user ID in the score into a
     *                                                       user to score.
     * @param LoggerInterface           $logger              Logger for what this repository does.
     */
    public function __construct(
        private LtiAssessment $ltiAssessment,
        private Assessment\LineItem\Model $ltiLineItem,
        private Assessment\Mapper $ltiAssessmentMapper,
        private Assessment\Score\Mapper $ltiScoreMapper,
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
            $this->logger,
        );
        $scoreModel = $scoreHelper->createScoreModel($score);
        try {
            $scoreModel = $scoreHelper->setStudentInScoreModel($score, $scoreModel);
            $scoreModel = $scoreHelper->setLtiAssessmentInScoreModel($scoreModel);
            $scoreModel = $scoreModel->setLtiLineItem($this->ltiLineItem);
            $scoreHelper->assertNoMoreRecentPreviousScore($scoreModel, $this->ltiLineItem);
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
