<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile\Lookup;

use Schoolbox\External\Connector\Sentral\Base\Endpoint\IApi;

/**
 * A list of known campuses according to Sentral
 * This is used to do a quick campus name lookup via an id
 */
class Campus
{
    /**
     * Prevent infinite loop of api calls
     */
    private const MAX_CALLS = 100;


    /**
     * A list of campus (id, name) tuples
     *
     * @var null|array
     */
    private ?array $lookup = null;


    /**
     * @param IApi $api
     */
    public function __construct(private IApi $api)
    {
    }


    /**
     * Generat a list of (id, name) tuples
     *
     * @return array
     */
    public function generate(): array
    {
        /* Don't refetch if we already did it once before
         */
        if (null !== $this->lookup) {
            return $this->lookup;
        }

        $this->lookup = [];

        $nextUrl = "enrolments/campus?limit=200";

        for ($i = 0; $nextUrl && $i < self::MAX_CALLS; $i++) {
            $payload = $this->api->retrieveDocument($nextUrl);

            foreach ($payload['data'] as $record) {
                $id   = $record['id'];
                $name = $record['attributes']['name'];

                $this->lookup[$id] = $name;
            }
        }

        return $this->lookup;
    }
}
