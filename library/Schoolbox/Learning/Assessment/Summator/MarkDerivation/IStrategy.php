<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Summator\MarkDerivation;

use Schoolbox\Learning\Assessment\Participant;

/**
 * Interface for strategies for deriving the normalised mark for a participant.
 */
interface IStrategy
{
    /**
     * Derive the normalised mark for a participant.
     *
     * @param Participant $participant
     * @param bool        $publishedOnly
     * @return float|null
     */
    public function deriveNormalisedMark(Participant $participant, bool $publishedOnly): ?float;
}
