<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

class Tokenhighlight implements IStrategy
{
    /**
     * @inheritDoc
     *
     * @param array $question
     * @return array|null
     */
    public function findMeaning(array $question): ?array
    {
        /* Use a regex to find all the lrn_token spans used to denote selectable options
         */
        preg_match_all('#<?span class="lrn_token">(.*?)</span\b[^>]*>#ms', $question['data']['template'], $matches);

        return $matches[0];
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

        return implode(IStrategy::TOKENISER, $vals);
    }
}