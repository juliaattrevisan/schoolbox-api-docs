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
 * Grab all the Health everywhere
 */
class Health
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

            $generator = $this->api->retrieve('GET', 'health/patients', Profile::SCOPE);

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
                    foreach (['allergies_symptoms', 'current_medications', 'general_medical_notes'] as $field) {
                        if ('' !== trim((string)$record[$field])) {
                            $this->lookup[$externalId] = true;
                        }
                    }
                }
            }
        }

        return $this->lookup;
    }
}
