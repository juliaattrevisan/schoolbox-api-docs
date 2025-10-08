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
            $watchlist = new Watchlist();

            $generator = $this->api->retrieve('GET', 'academics/enrollments', Classes::SCOPE);

            foreach ($generator as $page) {
                /* Avengers Assemble!
                 */
                foreach ($page['data'] as $record) {
                    $externalId = trim((string)$record['person_id']);

                    /* Only compile enrolments for guys on the watchlist
                     */
                    if (!$watchlist->isOnWatchlist($externalId, $this->watchlist)) {
                        continue;
                    }

                    $this->lookup[$externalId][$record['internal_class_id']] = $record;
                }
            }
        }

        return $this->lookup;
    }
}
