<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

class LongtextV2 implements IStrategy
{
    /**
     * @inheritDoc
     *
     * @param array $question
     * @return array|null
     */
    public function findMeaning(array $question): ?array
    {
        return null;
    }


    /**
     * @inheritDoc
     *
     * @param array $response
     * @return string|null
     */
    public function findHumanReadable(array $structure, array $response): ?string
    {
        return \Scrub::html2text($response['response']['value'] ?? '');
    }
}
