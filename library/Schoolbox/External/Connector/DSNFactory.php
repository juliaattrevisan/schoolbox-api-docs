<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector;

/**
 * Spawns a DSN instance based on your selection
 */
class DSNFactory
{

    /**
     * Produces a specific DSN codec for the selected suds importer
     *
     * @param string $type
     * @return \Schoolbox\External\Connector\Wonde\Base\Endpoint\Codec\DSN|null
     */
    public function produce(string $type): ?Wonde\Base\Endpoint\Codec\DSN
    {
        switch ($type) {
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
     * @throws \DomainException
     */
    public function produceDSNParts(string $type, string $key): ?array {
        $dsn = $this->produce($type);

        if ($dsn) {
            return $dsn->decode($key);
        }

        return null;
    }


    /**
     * Using the suds data source $type,
     * Spawn a DSN codec and encode the pieces $dsnParts into its corresponding dsn string
     *
     * @param string $type
     * @param array  $dsnParts
     * @return string|null
     */
    public function produceDSNString(string $type, array $dsnParts): ?string {
        $dsn = $this->produce($type);

        if ($dsn) {
            return $dsn->encode($dsnParts);
        }

        return null;
    }
}
