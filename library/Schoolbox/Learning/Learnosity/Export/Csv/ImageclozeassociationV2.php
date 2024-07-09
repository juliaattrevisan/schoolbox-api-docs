<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

class ImageclozeassociationV2 implements IStrategy
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

        foreach ($response['response']['value'] ?? [] as $data) {
            if (!isset($data[0])) {
                $vals[] = '';
            } else {
                $vals[] = $data[0];
            }
        }

        return implode(IStrategy::IMPLODER, $vals);
    }
}
