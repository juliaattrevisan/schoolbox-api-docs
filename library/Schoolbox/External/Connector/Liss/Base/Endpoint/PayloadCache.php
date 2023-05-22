<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use Alaress\IDatabase;
use PhpXmlRpc\Request;

/**
 * Cache a pushed payload locally
 */
class PayloadCache
{
    /**
     * Constructor.
     *
     * @param IDatabase $database
     */
    public function __construct(private IDatabase $database)
    {
    }


    /**
     * @param Request $message
     * @return void
     * @throws \JsonException
     */
    public function cache(Request $message): void
    {
        $payload = json_encode($message, JSON_THROW_ON_ERROR);

        /* Not a parser or reformatter
         * Just a thing that helps you inspect on the cli
         */
        $payload = strtr($payload, [
            '}' => "}\n<",
            '{' => "\n{"
        ]);

        $now = \Alaress_Date_Format::now()->getDatabase();

        $this->database->query("
            INSERT INTO log (data, created_at)
            VALUES (\"{$payload}\", \"{$now}\")
        ");
    }
}
