<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

interface IStrategy
{
    /**
     * Find the human-readable equivalent of students' responses which are often
     * given to you as an int so you have to cross-reference to find what that
     * integer really means to a human
     *
     * @param array $question
     * @return array|null
     */
    public function findMeaning(array $question): ?array;


    /**
     * Convert the integer response that a student gave,
     * finding its human-readable equivalent
     *
     * @param array $response
     * @return string|null
     */
    public function findHumanReadable(array $response): ?string;
}