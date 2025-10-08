<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar\Classes;
use Schoolbox\External\Connector\Watchlist;

/**
 * Grab all the Classes everywhere
 */
class Enrolments
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
     */
    public function __construct(private IApi $api, private array $watchlist)
    {
    }


    /**
     * Generate a compiled list of records, indexed by an identifier
     *
     * @return array
     */
    public function generate(): array
    {
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
                        'person_id' => $externalId
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
                $generator = $this->api->retrieve('GET', 'academics/enrollments', Classes::SCOPE);

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

        return $this->lookup;
    }
}
