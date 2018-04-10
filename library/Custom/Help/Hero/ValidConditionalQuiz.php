<?php
/**
 * ValidConditionalQuiz
 *
 * Determine when one, or both, of a conditional Quiz's Attempts were 100%
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Help\Hero;

class ValidConditionalQuiz
{

    /**
     * Identify conditionals were met
     *
     * @param array $userData
     * @param array $conditionalQuizzes
     * @return bool
     */
    public function isSatisifiedBy(array $userData, array $conditionalQuizzes): bool
    {
        $count = 0;

        /* One of the two options must be 100%
         */
        foreach ($conditionalQuizzes as $opt1 => $opt2) {
            $opt1Satisfied = $userData['assessments'][$opt1]['mark'] === (float)100;
            $opt2Satisfied = $userData['assessments'][$opt2]['mark'] === (float)100;

            if ($opt1Satisfied || $opt2Satisfied)  {
                $count++;
            }
        }

        return $count === \count($conditionalQuizzes);
    }
}