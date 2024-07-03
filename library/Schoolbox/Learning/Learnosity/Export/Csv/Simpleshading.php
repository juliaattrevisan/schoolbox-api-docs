<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

class Simpleshading implements IStrategy
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
        /* This is the wildest ride, but it seems like the 1th element indicates
         * which one was selected. The shaded boxes were 1st, 5th and 6th boxes.
         * Take a guess for now, as best we can
         *
            [response] => Array
                (
                    [value] => Array
                        (
                            [0] => Array
                                (
                                    [0] => 1
                                    [1] => 1
                                )

                            [1] => Array
                                (
                                    [0] => 1
                                    [1] => 5
                                )

                            [2] => Array
                                (
                                    [0] => 1
                                    [1] => 6
                                )

         */
        $vals = [];

        foreach ($response['response']['value'] ?? [] as $data) {
            $vals[] = $data[1];
        }

        return implode(IStrategy::IMPLODER, $vals);
    }
}
