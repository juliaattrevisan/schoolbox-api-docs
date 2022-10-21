<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Summator\MarkDerivation;

use Schoolbox\Learning\Assessment\Participant;

/**
 * Strategy for deriving the normalised mark for a participant:
 * a student's mark is zero for an assessment which they have not yet received
 * feedback for.
 */
class ZeroBeforeFeedback implements IStrategy
{
    /**
     * @inheritdoc
     */
    public function deriveNormalisedMark(Participant $participant, bool $publishedOnly): ?float
    {
        $latestFeedback = $participant->getAuthoritativeFeedback($publishedOnly);
        if (!$latestFeedback) {
            return 0;
        }
        return $participant->getNormalisedMark($publishedOnly);
    }
}
