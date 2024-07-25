<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Help\Superstar;

class Candidacy
{
    /**
     * Strings that denote what someone could be awarded
     */
    public const SUPERSTAR = 'superstar';
    public const STAR = 'star';
    public const STARTER = 'starter';


    /**
     * Floats that denote breakpoints between Superstardom,
     * Stardom, and nothing
     */
    private const SUPERSTAR_THRESHOLD = 100.00;
    private const STAR_THRESHOLD = 99.00;


    /**
     * Decide whether this person receives Superstardom, regular Stardom,
     * or nothing at all, based on their set of norm_marks
     *
     * ($normMarks is an array of norm_mark floats but might also be empty)
     *
     * @param array $normMarks
     * @return string|null
     */
    public function decide(array $normMarks): ?string
    {
        $superstar = true;
        $star = true;
        $starter = true;

        /* No grades here; someone in the megaquery came back with a project_result.mark
         * that was actually null in the db; that doesn't count for anything and will
         * have been filtered out prior
         */
        if (!\count($normMarks)) {
            return null;
        }

        /* Analyse each norm_mark obtained by this person
         */
        foreach ($normMarks as $normMark) {
            /* This person cannot possibly be a Superstar;
             * Find out if they can be a regular Star
             */
            if ($normMark < self::SUPERSTAR_THRESHOLD) {
                $superstar = false;

                /* This person cannot possibly be a Star;
                 * Ignore them entirely
                 */
                if ($normMark < self::STAR_THRESHOLD) {
                    $star = false;
                }
            }
        }

        /* Return the outcome
         */
        if ($superstar) {
            return self::SUPERSTAR;
        }

        if ($star) {
            return self::STAR;
        }

        return self::STARTER;
    }
}
