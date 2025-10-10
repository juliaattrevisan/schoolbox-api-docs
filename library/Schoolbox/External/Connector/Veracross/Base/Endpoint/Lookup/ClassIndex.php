<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar\Classes;

/**
 * Grab all the Classes everywhere
 */
class ClassIndex
{
    /**
     * Remember the output we might have previously generated
     *
     * @var array|null
     */
    private ?array $lookup = null;


    /**
     * Constructor
     *
     * @param IApi $api
     * @param array $watchlist
     * @param array $enrolmentsLookup
     * @param LoggerInterface $log
     */
    public function __construct(
        private IApi $api,
        private array $watchlist,
        private array $enrolmentsLookup,
        private LoggerInterface $log
    ) {
    }


    /**
     * Generate a compiled list of records, indexed by an identifier
     *
     * @return array
     */
    public function generate(): array
    {
        $this->log->debug('Prefetching classes...');

        $start = microtime(true);

        /* If we generated this lookup previously, just recycle it
         */
        if (null === $this->lookup) {
            $this->lookup = [];

            /* If a non-empty watchlist was provided, only obtain a specific list of classes
             * using individual calls to the api. Watchlist usage is a diagnostic only so you
             * would only be getting a handful of people plus their data: a few repeated calls
             * to the api is bearable in this case.
             *
             * Standard usage in the fleet would be to have an empty watchlist so we can just
             * use one api call and preprocess everyone's classes.
             */
            $watchlistEnabled = (bool)\count($this->watchlist);

            if ($watchlistEnabled) {
                /* Surgically obtain specific class data
                 */
                $specificClassIds = [];

                foreach ($this->enrolmentsLookup as $externalId => $enrolmentTuple) {
                    $specificClassIds = array_merge($specificClassIds, array_column($enrolmentTuple, 'internal_class_id'));
                }

                /* Flip the script for a faster lookup
                 */
                $specificClassIds = array_fill_keys($specificClassIds, true);
            }

            /* Find class codes and stuff
             */
            $generator = $this->api->retrieve('GET', 'classes', Classes::SCOPE);

            foreach ($generator as $page) {
                /* Avengers Assemble!
                 */
                foreach ($page['data'] as $record) {
                    /* No watchlist? Add every $record we encounter
                     * This should be the main use case for the fleet
                     */
                    if (!$watchlistEnabled) {
                        $this->lookup[$record['id']] = $this->minimise($record);
                        continue;
                    }

                    /* Otherwise only add the ones specifically for each $specificClassIds
                     * to help minimise memory consumption
                     */
                    if (isset($specificClassIds[$record['id']])) {
                        $this->lookup[$record['id']] = $this->minimise($record);
                    }
                }
            }
        }

        $end = microtime(true);

        $this->log->debug(sprintf(
            'Prefetching classes took %s seconds',
            number_format($end - $start, 2)
        ));

        $this->log->debug('');

        return $this->lookup;
    }


    /**
     * We only need some of the $record, not all of it; save some memory
     * by only keeping what we needed.
     *
     * @param array $record
     * @return array
     */
    private function minimise(array $record): array
    {
        return array_intersect_key($record, [
            'class_id' => true,
            'description' => true,
            'room' => true,
        ]);
    }
}
