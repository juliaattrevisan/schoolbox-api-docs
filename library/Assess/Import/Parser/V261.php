<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Assess\Import\Parser;

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
     */
    public function getMarkVisibility(): string
    {
        // Value is a valid IAssessable::MARK_VISIBILITY_* constant written by the 26.1+ exporter.
        return (string) $this->getXml()->markvisibility;
    }
}
