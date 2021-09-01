<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Timetable;

use Schoolbox\External\Connector\Sentral\Base\Endpoint\IApi;

/**
 * Class DefinitionCompiler
 *
 * A timetable definition for a given day is found with two endpoints which you
 * have to manually put together.
 *
 * timetables/timetable-calendar-date (tells you what Day this is, for a given date)
 * timetables/timetable-day           (tells you the Day's name)
 * timetables/timetable-period-in-day (tells you what Periods are associated with that Day)
 *
 * But it returns to you flat stacks, so you need to put them together yourself.
 * Then find the longest of the Days, because we presume a rectangular grid.
 *
 * This means we don't need to load everyone's timetable then derive the definition from
 * what we saw; we can superimpose what we saw on top of what we find here instead.
 *
 * I had to build this because the Homeroom and Recess and Lunch "subjects" aren't timetabled
 * subjects; they don't appear in the other endpoints. I went back to the first time I
 * built the sentral connector, and found the info here (finally)
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Timetable
 */
class DefinitionCompiler
{

    /**
     * Prevent infinite loop of api calls
     */
    private const MAX_CALLS = 100;


    /**
     * @var false|string
     */
    private $from;


    /**
     * @var false|string
     */
    private $to;


    /**
     * @var IApi
     */
    private $api;


    /**
     * DefinitionCompiler constructor.
     *
     * @param IApi   $api
     * @param string $from
     * @param string $to
     */
    public function __construct(IApi $api, string $from, string $to)
    {
        $this->from = $from;
        $this->to = $to;

        $this->api = $api;
    }


    /**
     * Compile a set of (date, period attributes) tuples
     * This will tell us all the periods on a given Day which occurred on a particular date
     *
     * From here you can infer the longest of the days and use that as our rectangular
     * definition, though I don't think this is something Sentral does, I can't disregard
     * the possibility of it secretly doing things that aren't documented
     */
    public function compile(): array
    {

        /* Find all the (dayId, period attributes) tuples everywhere
         * which essentially is a hashed lookup table
         */
        $periodsInDay = $this->compilePeriodsInDay();

        /* Sentral gives you paginated data, at most, 200 per page
         *
         * Process multiple pages until there are none remaining, or if something is really stuffed,
         * until MAX_CALLS is reached, to prevent infinite calls to a third party.
         */
        $nextUrl = "timetables/timetable-calendar-date?from={$this->from}&to={$this->to}&limit=200";

        $struct = [];

        for ($i = 0; $nextUrl && $i < self::MAX_CALLS; $i++) {
            $payload = $this->api->retrieveDocument($nextUrl);

            foreach ($payload['data'] as $timetableCalendarDate) {
                /* If, for some reason, the relationships node doesn't exist,
                 * then it probably means there are no Days for this date
                 * and we should just skip it
                 *
                 * I think that's what it means.
                 * Or their api could be broken.
                 * Or both (maybe they meant to return empty array in extant node)
                 */
                if (!isset($timetableCalendarDate['relationships'])) {
                    continue;
                }

                $dayId = $timetableCalendarDate['relationships']['day']['data']['id'];

                /* For each day, find all the associated periods, then stack them up
                 */
                $struct[$timetableCalendarDate['id']] = $periodsInDay[$dayId];
            }

            /* Prepare for next page if it exists
             */
            $nextUrl = $payload['links']['next'] ?? null;
        }

        return $struct;
    }


    /**
     * Collate a set of (dayId, attributes) tuples
     *
     * Their api is pretty much limited to "one, or everything" mentality so we basically
     * have to download this entire table and manually assemble things
     *
     * @return array
     */
    private function compilePeriodsInDay(): array
    {
        $nextUrl = 'timetables/timetable-period-in-day';

        $struct = [];

        for ($i = 0; $nextUrl && $i < self::MAX_CALLS; $i++) {
            $payload = $this->api->retrieveDocument($nextUrl);

            foreach ($payload['data'] as $timetablePeriodInDay) {
                $dayId = $timetablePeriodInDay['relationships']['day']['data']['id'];
                $attributes = $timetablePeriodInDay['attributes'];

                $struct[$dayId][] = $attributes;
            }

            /* Prepare for next page if it exists
             */
            $nextUrl = $payload['links']['next'] ?? null;
        }

        return $struct;
    }
}
