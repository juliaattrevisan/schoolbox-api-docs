<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Strategy;

interface IStrategy
{
    /**
     * The equivalent Learnosity concept as listed in
     * https://reference.learnosity.com/questions-api/questiontypes
     *
     * @return string
     */
    public function getLearnosityEquivalent(): string;


    /**
     * Convert schoolbox data $question into its learnosity equivalent
     *
     * @param array $question
     * @return array
     */
    public function convert(array $question): array;
}
