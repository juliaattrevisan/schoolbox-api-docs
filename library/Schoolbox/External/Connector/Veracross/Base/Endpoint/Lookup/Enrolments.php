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
use Schoolbox\External\Connector\Watchlist;

/**
 * Grab all the Enrolments everywhere
 */
class Enrolments
{
    /**
     * Presume the Veracross class statuses cannot be mutated by schools
     * These ints correspond to a human-readable value via a Value List
     * that you get with each payload page.
     *
     * From the sandbox instance:
     *     0: <None>
     *     1: Active
     *     2: Future
     *     3: Completed
     *     4: Canceled
     *     5: On Hold
     *
     * TODO Currently unknown if schools can modify this value list
     *  For now I have to presume immutability
     */

    private const CLASS_STATUS_ACTIVE = 1;

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
     * @param LoggerInterface $log
     */
    public function __construct(
        private IApi $api,
        private array $watchlist,
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
        $this->log->debug('Prefetching enrolments...');

        $start = microtime(true);

        /* If we generated this lookup previously, just recycle it
         */
        if (null === $this->lookup) {
            $this->lookup = [];

            /* If there was a specific set of external ids, obtain those separately
             * the long way: this is for diagnostic usages only and should be used
             * for a limited set of individuals.
             */
            $watchlistEnabled = (bool)\count($this->watchlist);

            if ($watchlistEnabled) {
                /* Segregated Import for a specific set of people
                 */
                foreach ($this->watchlist as $externalId) {
                    $generator = $this->api->retrieve('GET', 'academics/enrollments', Classes::SCOPE, [
                        'person_id' => $externalId,
                        'class_status' => self::CLASS_STATUS_ACTIVE,
                        'currently_enrolled' => true,
                    ]);

                    foreach ($generator as $page) {
                        /* Avengers Assemble!
                         */
                        foreach ($page['data'] as $record) {
                            $this->lookup[$externalId][$record['internal_class_id']] = $record;
                        }
                    }
                }
            } else {
                /* Otherwise just obtain all the enrolments.
                 * This is the main use case in the fleet.
                 */
                $generator = $this->api->retrieve('GET', 'academics/enrollments', Classes::SCOPE, [
                    'class_status' => self::CLASS_STATUS_ACTIVE,
                    'currently_enrolled' => true,
                ]);

                foreach ($generator as $page) {
                    /* Avengers Assemble!
                     */
                    foreach ($page['data'] as $record) {
                        $externalId = trim((string)$record['person_id']);

                        $this->lookup[$externalId][$record['internal_class_id']] = $record;
                    }
                }
            }
        }

        $end = microtime(true);

        $this->log->debug(sprintf(
            'Prefetching enrolments took %s seconds',
            number_format($end - $start, 2)
        ));

        $this->log->debug('');

        return $this->lookup;
    }
}
