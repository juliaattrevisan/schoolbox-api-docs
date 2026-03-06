<?php

namespace Schoolbox\Learning\Assessment\Reporting;

/**
 * This is just a statically-accessible lookup instance like I'd normally have written.
 */
enum Range: string
{
    /**
     * Consts correlating to the range options used by frontend world
     */
    case CURRENT_ACADEMIC_PERIOD = 'current_academic_period';
    case PREVIOUS_ACADEMIC_PERIOD = 'previous_academic_period';
    case CURRENT_AND_PREVIOUS_ACADEMIC_PERIOD = 'current_and_previous_academic_period';
    case CURRENT_ACADEMIC_YEAR = 'current_academic_year';
    case CURRENT_AND_PREVIOUS_ACADEMIC_YEAR = 'current_and_previous_academic_year';


    /**
     * Returns a key-value set in a convenient struct
     *
     * @return array
     */
    public static function lookup(): array
    {
        return [
            Range::CURRENT_ACADEMIC_PERIOD->value => 'Current academic period',
            Range::PREVIOUS_ACADEMIC_PERIOD->value => 'Previous academic period',
            Range::CURRENT_AND_PREVIOUS_ACADEMIC_PERIOD->value => 'Current and previous academic period',
            Range::CURRENT_ACADEMIC_YEAR->value => 'Current academic year',
            Range::CURRENT_AND_PREVIOUS_ACADEMIC_YEAR->value => 'Current and previous academic year',
        ];
    }
}
