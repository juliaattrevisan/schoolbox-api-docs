<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile\Profile;
use Schoolbox\External\Connector\Watchlist;

/**
 * A list of all the alerts per person.
 */
class Alert
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

            $generator = $this->api->retrieve('GET', 'academics/student_alerts', Profile::SCOPE);

            foreach ($generator as $page) {
                /* Avengers Assemble!
                 */
                foreach ($page['data'] as $record) {
                    /* omg why couldn't they have called this person_id instead...
                     * that's what id allegedly means
                     */
                    $externalId = trim((string)$record['id']);

                    /* Only import for specific users
                     */
                    if (!$watchlist->isOnWatchlist($externalId, $this->watchlist)) {
                        continue;
                    }

                    /* Record is a 4-tuple whose array nodes *should* all exist
                     * but sometimes they're empty/null. Note that these are multiline
                     * strings, not just one sentence.
                     *
                            [id] => 40622
                            [family_alert] =>
                            [medical_alert] =>
                            [general_alert] =>
                            [academic_alert] =>
                     */
                    $this->lookup[$externalId] = $record;
                }
            }
        }

        return $this->lookup;
    }
}
