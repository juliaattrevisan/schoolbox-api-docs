<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

class Classification implements IStrategy
{
    /**
     * @inheritDoc
     *
     * @param array $question
     * @return array|null
     */
    public function findMeaning(array $question): ?array
    {
        return $question['data']['possible_responses'];
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

        foreach ($response['response']['value'] as $data) {
            $column = [];

            /* Represent each column specially
             */
            foreach ($data as $val) {
                $column[] = $structure[$response['question_reference']]['data'][$val];
            }

            $vals[] = implode(IStrategy::IMPLODER, $column);
        }

        return implode(' | ', $vals);
    }
}