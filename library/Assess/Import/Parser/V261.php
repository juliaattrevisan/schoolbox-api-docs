<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Assess\Import\Parser;

use DomainException;
use Schoolbox\Learning\Assessment\IAssessable;

/**
 * v26.1 quiz variations — mark_visibility is now exported as a string.
 *
 * Prior versions exported mark_visibility as an integer; V160 maps those integers
 * to string constants. From 26.1 onwards the value is already a string constant,
 * so no mapping is required.
 */
class V261 extends V170
{
    /**
     * @return string
     * @throws DomainException
     */
    public function getMarkVisibility(): string
    {
        // Value should be an IAssessable::MARK_VISIBILITY_* constant written by the 26.1+ exporter.
        $markVisibility = trim((string) $this->getXml()->markvisibility);

        if (array_key_exists($markVisibility, IAssessable::MARK_VISIBILITY_STRINGS)) {
            return $markVisibility;
        }

        throw new DomainException(
            "Invalid mark visibility value '$markVisibility' in quiz import XML. Expected one of: " .
            implode(', ', array_keys(IAssessable::MARK_VISIBILITY_STRINGS)) . '.'
        );
    }
}
