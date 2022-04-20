<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector;

use Schoolbox\External\Importer\Endpoint\IDSN;

/**
 * This generally manages the production of DSN-related stuff like
 * a DSN codec itself, or the constituent pieces of their DSN strings;
 * this factory spawns:
 *
 *     - the DSN instance based on your selection
 *     - the DSN pieces for a given DSN string
 *     - the DSN string for a given set of DSN pieces.
 */
class DSNFactory
{

    /**
     * Produces a specific DSN codec for the selected suds importer
     *
     * @param string $type
     * @return IDSN|null
     */
    public function produce(string $type): ?IDSN
    {
        switch ($type) {
            case \Schoolbox\External\Importer\Endpoint\Factory::ISAMS:
                return new \Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN();

            case \Schoolbox\External\Importer\Endpoint\Factory::KAMAR:
                return new \Schoolbox\External\Connector\Kamar\Base\Endpoint\Codec\DSN();

            case \Schoolbox\External\Importer\Endpoint\Factory::SENTRAL:
                return new \Schoolbox\External\Connector\Sentral\Base\Endpoint\Codec\DSN();

            case \Schoolbox\External\Importer\Endpoint\Factory::TASS_API:
                return new \Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN();

            case \Schoolbox\External\Importer\Endpoint\Factory::COMPASS:
                return new \Schoolbox\External\Connector\Compass\Base\Endpoint\Codec\DSN();

            case \Schoolbox\External\Importer\Endpoint\Factory::WONDE:
                return new \Schoolbox\External\Connector\Wonde\Base\Endpoint\Codec\DSN();
        }

        return null;
    }


    /**
     * Using the suds data source $type,
     * Spawn a DSN codec and decode the string $key into its corresponding pieces
     *
     * @param string $type
     * @param string $key
     * @return array|null
     */
    public function produceDSNParts(string $type, string $key): ?array
    {
        $dsn = $this->produce($type);

        if ($dsn) {
            try {
                return $dsn->decode($key);
            } catch (\DomainException $e) {
                // noop - bad configuration is now something users can fix
                // since there's a ui to supply the dsn parts
            }
        }

        return null;
    }


    /**
     * Using the suds data source $type,
     * Spawn a DSN codec and encode the pieces $dsnParts into its corresponding dsn string
     *
     * $dsnParts will be null if you chose csv files in suds
     *
     * @param string     $type
     * @param array|null $dsnParts
     * @return string|null
     */
    public function produceDSNString(string $type, ?array $dsnParts): ?string
    {
        if (null === $dsnParts[$type]) {
            return null;
        }

        $dsn = $this->produce($type);

        if ($dsn) {
            return $dsn->encode($dsnParts[$type]);
        }

        return null;
    }
}
