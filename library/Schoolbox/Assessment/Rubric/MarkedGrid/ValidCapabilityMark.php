<?php

/**
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Learning\MarkScheme\TotalMarkAbstract;

class ValidCapabilityMark extends \Alaress\Specification\Base
{
    private $capabilityMax;
    private $markScheme;

    public function __construct($capabilityMax, $markScheme)
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

        // Percentage mark types (Letter Grade and Percentage) are always sent to the backend as values between 0-100
        $max = $this->markScheme instanceof TotalMarkAbstract ? $this->capabilityMax : 100;

        // TODO for debugging only, remove later
        $isNumeric = is_numeric($capabilityMark);
        $greaterOrEqualToZero = $capabilityMark >= 0;
        $lessThanOrEqualToMax = $capabilityMark <= $max;
        return $isNumeric && $greaterOrEqualToZero && $lessThanOrEqualToMax;
    }
}
