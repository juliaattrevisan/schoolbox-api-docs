<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use PhpXmlRpc\Request;
use Schoolbox\Resource\Storage\Mapper;
use Schoolbox\Resource\Storage\Model;

/**
 * Cache a pushed payload locally
 */
class PayloadCache
{
    /**
     * Constructor.
     *
     * @param Mapper $storageMapper
     */
    public function __construct(private \Schoolbox\Resource\Storage\Mapper $storageMapper)
    {
    }


    /**
     * @param Request $message
     * @return Model
     * @throws \JsonException
     */
    public function cache(Request $message): \Schoolbox\Resource\Storage\Model
    {
        $payload = json_encode($message, JSON_THROW_ON_ERROR);

        /* Not a parser or reformatter
         * Just a thing that helps you inspect on the cli
         */
        $payload = strtr($payload, [
            '}' => "}\n",
            '{' => "\n{"
        ]);

        $now = \Alaress_Date_Format::now();
        $expiryAt = \Alaress_Date_Format::now()->addWeeks(1);

        $filename = sprintf(
            'suds_liss_%s.log',
            $now->getDatabase()
        );

        return $this->storageMapper->acceptStream($filename, $payload, false, $expiryAt);
    }
}
