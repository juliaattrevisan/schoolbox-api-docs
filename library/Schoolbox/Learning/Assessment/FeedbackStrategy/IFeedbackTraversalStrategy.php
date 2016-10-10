<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\FeedbackStrategy;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Participant;

interface IFeedbackTraversalStrategy
{
    /**
     * Gets the current authoritative Feedback for the given Participant and Assessment.
     *
     * @param Participant $participant
     * @param IAssessable $assessable
     * @param bool $publishedOnly
     * @return IFeedback|null
     */
    public function getMostRecentFeedback($participant, $assessable, $publishedOnly);
}