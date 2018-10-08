<?php
/**
 *
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

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
    public function __construct($markPerCap)
    {
        $this->markPerCap = $markPerCap;
    }


    /**
     * `$capabilityMax` satisfies this pattern if either:
     * * `$this->markPerCap` is false
     * * `$capabilityMax` is numeric and > 0
     *
     * @param mixed $capabilityMax
     * @return bool
     */
    public function isSatisfiedBy($capabilityMax) : bool
    {
        // TODO this is necessary because the assessment doesn't enforce strict typing and doesn't cast toggle values to bool...ugh
        if ($this->markPerCap === true || $this->markPerCap === 'on') {
            return is_numeric($capabilityMax) && $capabilityMax > 0;
        }
        return true;
    }
}
