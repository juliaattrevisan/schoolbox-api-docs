<?php

/**
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Alaress\Specification\Base;
use InvalidArgumentException;
use RangeException;
use Schoolbox\Learning\MarkScheme\IMarkScheme;

class ValidCapabilityMark extends Base
{
    private IMarkScheme $markScheme;

    public function __construct(IMarkScheme $markScheme)
    {
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

        try {
            $normalisedCapabilityMark = $this->markScheme->getNormalisedMark($capabilityMark);
        } catch (RangeException | InvalidArgumentException $e) {
            // This cap mark is out of range or an unparseable value.
            // Either way:
            return false;
        }
        return $normalisedCapabilityMark >= IMarkScheme::NORMALISED_MARK_MIN
            && $normalisedCapabilityMark <= IMarkScheme::NORMALISED_MARK_MAX;
    }
}
