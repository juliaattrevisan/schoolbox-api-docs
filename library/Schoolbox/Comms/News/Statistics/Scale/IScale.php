<?php

/**
 * IScale
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Scale;

use Alaress_Date_Format;

interface IScale
{

    /**
     * Get the sql representing the aggregation of each strategy's setting
     *
     * @return string
     */
    public function getSql(): string;


    /**
     * Number of minutes to add when generating date placeholders in generateDateRange()
     *
     * @return int
     */
    public function getInterval(): int;


    /**
     * Generate placeholder timestamps for periodic and regular interval reporting
     *
     * There's a case where visual anomalies can be rendered.
     *
     * The way the strat works is that it takes all the interactions for a month and assumes them all to
     * have happened in the first day of that month, for the sake of compiling data for highcharts.
     *
     * The date range is specified as PERIOD_LENGTH before the current time, so if it's Aug 22 now, the
     * date range will be Jul 22 - Aug 22. However the month's data is simulated to occur on Jul 1. That's
     * in the sql's GROUP BY statement.
     *
     * highcharts doesn't seem to understand this situation and plots a random data point out of order so
     * on a two-month graph, you get three data points for no obvious reason.
     *
     * To avoid it we can allow each strat to provide a date range unique to its own situation
     *
     * @param Alaress_Date_Format $start
     * @param Alaress_Date_Format $end
     * @return array
     */
    public function generateDateRange(Alaress_Date_Format $start, Alaress_Date_Format $end): array;
}
