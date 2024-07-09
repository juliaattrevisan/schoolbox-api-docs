<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

class Orderlist implements IStrategy
{
    /**
     * @inheritDoc
     *
     * @param array $question
     * @return array|null
     */
    public function findMeaning(array $question): ?array
    {
        return $question['data']['list'];
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

        foreach ($response['response']['value'] ?? [] as $key => $val) {
            if ('' === trim((string)$val)) {
                $vals[] = '';
            } else {
                $vals[] = $structure[$response['question_reference']]['data'][$val];
            }
        }

        return implode(IStrategy::IMPLODER, $vals);
    }
}
