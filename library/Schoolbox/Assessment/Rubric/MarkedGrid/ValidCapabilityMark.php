<?php

/**
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\MarkScheme\TotalMarkAbstract;

class ValidCapabilityMark extends \Alaress\Specification\Base
{
    private ?float $capabilityMax;
    private IMarkScheme $markScheme;

    public function __construct(?float $capabilityMax, IMarkScheme $markScheme)
    {
        $this->capabilityMax = $capabilityMax;
        $this->markScheme = $markScheme;
    }

    // Marks are intentionally validated in their scaled form here; it's easiest to compare the exact value the user typed in
    // with the capability max to ensure it's within the range.
    public function isSatisfiedBy($capabilityMark): bool
    {
        if (null === $capabilityMark) {
            // This is a self reflection, so null cap mark is expected
            return true;
        }

        $normalisedCapabilityMark = $this->markScheme->getNormalisedMark($capabilityMark);
        return $normalisedCapabilityMark >= IMarkScheme::NORMALISED_MARK_MIN
            && $normalisedCapabilityMark <= IMarkScheme::NORMALISED_MARK_MAX;
    }
}
