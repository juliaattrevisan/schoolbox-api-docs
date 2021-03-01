<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Evaluation\Answer;

/**
 * Method for comparing string answers to questions.
 *
 * (Extracted from `Evaluation_Answer_Exact`.)
 */
trait Comparison
{
    /**
     * Compares two strings for the purposes of marking exact answer.
     *
     * @param string $str1
     * @param string $str2
     * @return bool True when identical, false when not.
     */
    protected function compareStrings(string $str1, string $str2): bool
    {
        return strcmp($this->normalizeForComparison($str1), $this->normalizeForComparison($str2)) === 0;
    }


    /**
     * Normalizes response and answer for comparison:
     * * replaces all whitespaces sequences with a single space
     * * converts to lower case
     * * replaces em and en dash character with standard hyphen
     * * standardises multiply and divide symbols
     *
     * @param string $str
     * @return string
     */
    private function normalizeForComparison(string $str): string
    {
        $equivalences = strtr(
            $str,
            [
                '×' => 'x',
                '÷' => '/',
                '—' => '-',
                '–' => '-',
            ]
        );

        return mb_strtolower(trim(preg_replace('/\\s+/', ' ', $equivalences)), 'UTF-8');
    }
}
