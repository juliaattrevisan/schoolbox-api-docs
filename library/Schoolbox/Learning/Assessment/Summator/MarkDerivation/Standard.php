<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Summator\MarkDerivation;

use Schoolbox\Learning\Assessment\Participant;

/**
 * Standard strategy for deriving the normalised mark for a participant:
 * simply retrieve it directly from the participant record.
 */
class Standard implements IStrategy
{
    /**
     * @inheritdoc
     */
    public function deriveNormalisedMark(Participant $participant, bool $publishedOnly): ?float
    {
        return $participant->getNormalisedMark($publishedOnly);
    }
}
