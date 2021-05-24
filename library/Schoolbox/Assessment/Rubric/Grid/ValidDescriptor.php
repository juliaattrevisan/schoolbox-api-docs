<?php

/**
 *
 * @author      dbaker <dorothea.baker@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

class ValidDescriptor extends \Alaress\Specification\Base
{
    private $usesMarkRange;

    public function __construct(bool $usesMarkRange)
    {
        $this->usesMarkRange = $usesMarkRange;
    }

    /**
     * @param string $descriptorValue
     * @return bool
     */
    public function isSatisfiedBy($descriptorValue): bool
    {
        // Null descriptor points are ok.
        if ('' === $descriptorValue) {
            return true;
        }
        $usesMarkRange = $this->usesMarkRange;
        $hasDecimals = $this->hasDecimals($descriptorValue);
        return is_numeric($descriptorValue) && (float)$descriptorValue >= 0 && !($usesMarkRange && $hasDecimals);
    }

    /**
     * @param string $descriptorValue
     * @return bool
     */
    private function hasDecimals(string $descriptorValue): bool
    {
        return preg_match('/\.\d+$/', $descriptorValue) === 1;
    }
}
