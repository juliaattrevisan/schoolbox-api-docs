<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

class Explorer implements IStrategy
{

    public function findMeaning(array $question): ?array
    {
        new \Alaress_Probe($question);
    }

    public function findHumanReadable(array $structure, array $response): ?string
    {
        new \Alaress_Probe($response);
    }
}
