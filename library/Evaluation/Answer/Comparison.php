<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Evaluation\Answer;

use Collator;

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
    public function compareStrings(string $str1, string $str2): bool
    {
        // The locale doesn't really matter for equality testing
        $collator = new Collator('root');

        // Secondary strength differentiates accents, but not cases.
        // (Collator::ALTERNATE_HANDLING: Collator::SHIFTED disregards spaces,
        //  punctuation and symbols: this is too extreme, spaces are significant
        //  but need to be normalized, punctuation and symbols are assumed
        //  significant)
        $collator->setStrength(Collator::SECONDARY);

        // Compare
        $comparison = $collator->compare(
            $this->normalizeForComparison($str1),
            $this->normalizeForComparison($str2),
        );
        if ($comparison === 0) {
            return true;
        }
        if ($comparison === false) {
            // data is broken, trigger a warning
            trigger_error(
                'Cannot compare actual and given answer: one or the other is incorrectly encoded',
                E_USER_WARNING
            );
        }
        return false;
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
                '’' => "'",
                '“' => '"',
                '”' => '"',
                'ˮ' => '"',
                'ʺ' => '"',
            ]
        );

        return mb_strtolower(trim(preg_replace('/\\s+/', ' ', $equivalences)), 'UTF-8');
    }
}
