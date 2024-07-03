<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

class Numberlineplot implements IStrategy
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
        $vals = [];

        /* This might be too cryptic for anyone other than math nerds, but whatever
         */
        foreach ($response['response']['value'] ?? [] as $coords) {
            if (isset($coords['point2'])) {
                $vals[] = sprintf(
                    '%s:%s:%s',
                    $coords['type'],
                    $coords['point1'],
                    $coords['point2']
                );
            } else {
                $vals[] = sprintf(
                    '%s:%s',
                    $coords['type'],
                    $coords['point1']
                );
            }
        }

        return implode(IStrategy::IMPLODER, $vals);
    }
}