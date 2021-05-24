<?php

/**
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

/**
 * Spec pattern for validating capability max marks.
 */
class ValidCapabilityMax extends \Alaress\Specification\Base
{
    /**
     * @var bool
     */
    private $markPerCap;


    /**
     * Constructor
     *
     * @param bool $markPerCap Is the assessment using mark per capability?
     */
    public function __construct(bool $markPerCap)
    {
        $this->markPerCap = $markPerCap;
    }


    /**
     * `$capabilityMax` satisfies this pattern if either:
     * * `$this->markPerCap` is false
     * * `$capabilityMax` is numeric and >= 0
     *
     * @param mixed $capabilityMax
     * @return bool
     */
    public function isSatisfiedBy($capabilityMax): bool
    {
        if ($this->markPerCap) {
            return is_numeric($capabilityMax) && $capabilityMax >= 0;
        }
        return true;
    }
}
