<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\FeedbackStrategy;

use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Participant;

/**
 * @deprecated Logic has been absorbed
 * @see        Participant
 */
interface IFeedbackTraversalStrategy
{
    /**
     * Gets the current authoritative Feedback for the given Participant and Assessment.
     *
     * @param Participant $participant
     * @param bool $publishedOnly
     * @return IFeedback|null
     */
    public function getMostRecentFeedback(Participant $participant, $publishedOnly);
}
