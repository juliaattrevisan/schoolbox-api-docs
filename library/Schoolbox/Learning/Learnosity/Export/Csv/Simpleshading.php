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
        /* This is the wildest ride, but the array elements here correspond to coordinates.
         * Simple Shading by default gives you one row, but you can expand it to have multiple rows/columns.
         *
         * Each element of $value represents a shaded square.
         * 0th element corresponds to a row and the 1th element corresponds to a column, both of which describe
         * the coordinates of shaded square. This is an annoying (and unconventional) expression of (y,x)
         *
         * In this example below, the shaded boxes were 1st, 5th and 6th boxes.
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
            /* Forcibly represent as (x,y) corresponding to (column, row)
             */
            $vals[] = sprintf(
                '(%s,%s)',
                $data[1],
                $data[0]
            );
        }

        /* Maybe use TOKENISER instead, so there are distinct characters...one day
         */
        return implode(IStrategy::IMPLODER, $vals);
    }
}
