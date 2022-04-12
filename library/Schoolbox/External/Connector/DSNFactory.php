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

    public function produceDSNParts() {}
    public function produceDSNString() {}
}
