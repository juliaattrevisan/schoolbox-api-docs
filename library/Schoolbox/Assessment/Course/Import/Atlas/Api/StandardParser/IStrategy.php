<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api\StandardParser;

/**
 * Interface for defining parsers for assessment standards from Rubicon Atlas.
 *
 * This is required because the Atlas API returns standards as strings, which
 * may or may not contain a recognizable curriculum code.
 */
interface IStrategy
{
    /**
     * Parses a string standard into a title and code.
     *
     * @param string $standard
     * @return null|array Array with "title" and "code" keys if this standard
     *                    can understand this string value, null otherwise.
     */
    public function parseStandard(string $standard): ?array;
}
