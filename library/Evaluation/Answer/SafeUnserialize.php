<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Evaluation\Answer;

/**
 * Unserializes response/answer values without raising a warning for null,
 * empty, or malformed data.
 *
 * (Extracted from `Evaluation_Answer`.)
 */
trait SafeUnserialize
{
    /**
     * Unserializes a response/answer value, treating null, empty, and
     * malformed data as "no value" instead of raising a warning.
     *
     * `assess_response.response` is legitimately NULL for unanswered
     * questions, and historical rows may contain non-serialized garbage;
     * neither should surface as a PHP warning.
     *
     * @param mixed $value
     * @return mixed The unserialized value, or false if $value isn't valid
     *               serialized data.
     */
    protected function safeUnserialize($value)
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        return @unserialize($value);
    }
}
