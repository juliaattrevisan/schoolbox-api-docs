<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Timetable;

use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;

/**
 * Class DayCalibrator
 *
 * isams uses a 1-indexed array to represent its days of the week.
 * But schoolbox uses a 0-index scheme
 *
 * This class specifically recalibrates the xml processing so that
 * this difference is taken into account such that the start of the isams
 * week is mapped to schoolbox Day 1
 *
 * (Schools can configure the start of the week in isams so that it starts
 * on Sunday, or Monday, or Tuesday...etc. but the start of that week should
 * match to schoolbox Day 1)
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint\Timetable
 */
class DayCalibrator
{

    /**
     * @var array
     */
    private array $api;


    /**
     * @var int
     */
    private int $offset;


    /**
     * DayCalibrator constructor.
     *
     * @param array $api
     */
    public function __construct(array $api)
    {
        $this->api = $api;
        $this->offset = $this->calculateOffset();
    }


    /**
     * Calculate the difference between the isams start of the week
     * and the presumed schoolbox start of the week
     */
    private function calculateOffset(): int
    {
        /* Find the first Day node
         */
        $firstDayIndex = (int)$this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/TimetableManager/Structure/Week/Days/Day")[0]->Day[0];

        /* Calculate the difference between that Day's value and 1
         * This is the offset that needs to apply to all Days in this import
         */
        return $firstDayIndex - 1;
    }


    /**
     * Figure out the equivalent schoolbox day for a given isams day
     *
     * @param int $isamsDay
     * @return int
     */
    public function determineSchoolboxDay(int $isamsDay): int
    {
        return $isamsDay - $this->offset;
    }
}
