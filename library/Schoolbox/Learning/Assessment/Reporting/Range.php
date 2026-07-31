<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Reporting;

use Alaress_Date_Format;
use Schoolbox\Assessment\Term\Mapper as TermMapper;

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

    /**
     * Resolve this configured supporting-data range from one reference date.
     *
     * Term boundaries are cloned because markbook callers normalise their times
     * in place and must not alter dates held by persisted term models.
     *
     * @param Alaress_Date_Format $referenceDate Date on which the reporting page is loaded.
     * @param TermMapper $termMapper Mapper used to find ordinary academic periods.
     * @return array{from: Alaress_Date_Format, to: Alaress_Date_Format}|null Inclusive date boundaries, or null when a required period is unavailable.
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function resolve(Alaress_Date_Format $referenceDate, TermMapper $termMapper): ?array
    {
        if ($this === self::CURRENT_ACADEMIC_YEAR) {
            return [
                'from' => $referenceDate->getStartOfSchoolYear(),
                'to' => $referenceDate->endOfSchoolYear(),
            ];
        }

        if ($this === self::CURRENT_AND_PREVIOUS_ACADEMIC_YEAR) {
            return [
                'from' => (clone $referenceDate)->getStartOfSchoolYear()->addYears(-1),
                'to' => $referenceDate->endOfSchoolYear(),
            ];
        }

        $currentTerm = $termMapper->findForDate($referenceDate);
        if ($currentTerm->getId() === null) {
            return null;
        }

        $currentFrom = clone $currentTerm->getStart();
        $currentTo = clone $currentTerm->getDisplayFinish();

        if ($this === self::CURRENT_ACADEMIC_PERIOD) {
            return [
                'from' => $currentFrom,
                'to' => $currentTo,
            ];
        }

        $previousTerm = null;
        foreach ($termMapper->findAllNonExtraordinary() as $term) {
            if ($term->getStart()->lt($currentTerm->getStart())) {
                $previousTerm = $term;
                break;
            }
        }

        if ($this === self::PREVIOUS_ACADEMIC_PERIOD) {
            if ($previousTerm === null) {
                return null;
            }

            return [
                'from' => clone $previousTerm->getStart(),
                'to' => clone $previousTerm->getDisplayFinish(),
            ];
        }

        return [
            'from' => $previousTerm === null ? $currentFrom : clone $previousTerm->getStart(),
            'to' => $currentTo,
        ];
    }
}
