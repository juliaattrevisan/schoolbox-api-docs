<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

class Numberline implements IStrategy
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

        /* For some reason there's a y coordinate on a singular axis, and
         * for some reason, it was always 7. Whatever that is, ignore it
         * for now, I suppose.
         */
        foreach ($response['response']['value'] ?? [] as $label => $coords) {
            $vals[] = sprintf(
                '%s:%s',
                $label,
                $coords['x']
            );
        }

        return implode(IStrategy::IMPLODER, $vals);
    }
}
