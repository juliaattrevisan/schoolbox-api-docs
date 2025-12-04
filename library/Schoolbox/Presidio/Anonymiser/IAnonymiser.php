<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Presidio\Anonymiser;

/**
 * Interface for anonymisers that Presidio supports.
 */
interface IAnonymiser
{
    /**
     * Build a payload for this anonymiser, when it's sent to Presidio.
     *
     * @return array
     */
    public function buildPayload(): array;
}
