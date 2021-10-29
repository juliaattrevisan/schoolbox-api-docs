<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Score;

use OAT\Library\Lti1p3Ags\Model\Score\ScoreInterface;
use OAT\Library\Lti1p3Ags\Repository\ScoreRepositoryInterface;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\LTI\Assessment\Score\Mapper as LtiScoreMapper;

class SingleAssessmentScoreRepository implements ScoreRepositoryInterface
{
    /**
     * The LTI assessment to save scores for.
     */
    private LtiAssessment $ltiAssessment;

    /**
     * The mapper used to save LTI scores.
     */
    private LtiScoreMapper $ltiScoreMapper;

    /**
     * Constructor
     *
     * @param LtiAssessment  $model
     * @param LtiScoreMapper $scoreMapper
     */
    public function __construct(LtiAssessment $model, LtiScoreMapper $scoreMapper)
    {
        $this->ltiAssessment  = $model;
        $this->ltiScoreMapper = $scoreMapper;
    }

    /**
     * Save the score for this repository's assessment.
     *
     * TODO implement
     *
     * @param ScoreInterface $score
     * @return ScoreInterface
     */
    public function save(ScoreInterface $score): ScoreInterface
    {
        return $score;
    }
}
