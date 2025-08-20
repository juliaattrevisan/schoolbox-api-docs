<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile\Profile;
use Schoolbox\External\Connector\Watchlist;

class Photo
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

            $generator = $this->api->retrieve('GET', 'person_photos', Profile::SCOPE);

            foreach ($generator as $page) {
                /* Avengers Assemble!
                 */
                foreach ($page['data'] as $record) {
                    $externalId = trim((string)$record['id']);

                    /* Only import for specific users
                     */
                    if (!(new Watchlist())->isOnWatchlist($externalId, $this->watchlist)) {
                        continue;
                    }

                    /* Overwrite with lookupped values
                     * If any of the following fields has contents, then they have a Medical Alert
                     */
                    $this->lookup[$externalId] = [
                        'url' => $record['download_url'],
                        'modifiedAt' => $record['last_modified_date'],
                    ];
                }
            }
        }

        return $this->lookup;
    }
}
