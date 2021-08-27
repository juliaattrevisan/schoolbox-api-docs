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

/**
 * Spec pattern for whether a given human-readable mark is an appropriate mark
 * for a capability.
 */
class ValidCapabilityMark extends Base
{
    /**
     * The marking scheme used to validate the human-readable mark.
     */
    private IMarkScheme $markScheme;

    /**
     * Constructor
     *
     * @param IMarkScheme $markScheme
     */
    public function __construct(IMarkScheme $markScheme)
    {
        $this->markScheme = $markScheme;
    }

    /**
     * Is the given human-readable capability mark valid for the capability's
     * mark scheme?
     *
     * @param mixed $candidate A candidate human-readable capability mark.
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        if (null === $candidate) {
            // This is a self reflection, so null cap mark is expected
            return true;
        }

        try {
            $normalisedCapabilityMark = $this->markScheme->getNormalisedMark($candidate);
        } catch (RangeException | InvalidArgumentException $e) {
            // This cap mark is out of range or an unparseable value.
            // Either way:
            return false;
        }
        return $normalisedCapabilityMark >= IMarkScheme::NORMALISED_MARK_MIN
            && $normalisedCapabilityMark <= IMarkScheme::NORMALISED_MARK_MAX;
    }
}
