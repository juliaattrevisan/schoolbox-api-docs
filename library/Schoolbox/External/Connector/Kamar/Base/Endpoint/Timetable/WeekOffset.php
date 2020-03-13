<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;

use Alaress_Date_Format;
use Schoolbox\Assessment\Term;

/**
 * Class WeekOffset
 *
 * Calculate the week offset from the start of the academic term
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable
 */
class WeekOffset
{

    /**
     * @var Term\Mapper
     */
    private $termMapper;


    /**
     * WeekOffset constructor.
     *
     * @param Term\Mapper $termMapper
     */
    public function __construct(Term\Mapper $termMapper)
    {
        $this->termMapper = $termMapper;
    }


    /**
     * Determine which week we're in, if possible
     * Returns null when it can't be determined
     *
     * @param Alaress_Date_Format $date
     * @return int|null
     */
    public function findWeekOffset(Alaress_Date_Format $date): ?int
    {
        $term = $this->termMapper->findForDate($date);

        /* Term not found
         */
        if (!$term->getId()) {
            return null;
        }

        $startWeek = (int)date('W', $term->getStart()->getUnixTimestamp());
        $currentWeek = (int)date('W', $date->getUnixTimestamp());

        /* Include the current week in calculations using a +1 offset
         * e.g. if term begins on 8/1/20,
         *     8/1/20, 9/1/20 and 10/1/20 are in week 1
         *     11/1/20 is in week 2
         *     17/1/20 is in week 2
         *     21/2/20 is in week 3
         */
        $offset = $currentWeek - $startWeek + 1;

        if ($offset > 0) {
            return $offset;
        }

        return null;
    }
}
