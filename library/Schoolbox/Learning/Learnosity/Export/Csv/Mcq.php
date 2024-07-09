<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

class Mcq implements IStrategy
{
    /**
     * @inheritDoc
     *
     * @param array $question
     * @return array|null
     */
    public function findMeaning(array $question): ?array
    {
        /* Find human-readable meanings for options. Multiple choice questions will have numeric values
         * to represent student responses, but we want to show the human-readable equivalent.
         */
        $lookup = [];

        foreach ($question['data']['options'] as $option) {
            $lookup[$option['value']] = $option['label'];
        }

        return $lookup;
    }


    /**
     * @inheritDoc
     *
     * @param array $response
     * @return string|null
     */
    public function findHumanReadable(array $structure, array $response): ?string
    {
        $vals = [];

        foreach ($response['response']['value'] ?? [] as $val) {
            $vals[] = $structure[$response['question_reference']]['data'][$val];
        }

        return implode(IStrategy::IMPLODER, $vals);
    }
}
