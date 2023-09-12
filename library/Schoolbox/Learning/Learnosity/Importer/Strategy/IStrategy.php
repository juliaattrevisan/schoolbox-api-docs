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
     * Convert schoolbox data $question into its learnosity equivalent
     *
     * @param array $question
     * @return array
     */
    public function convert(array $question): array;
}
