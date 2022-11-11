<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use Alaress\Lumberjack\Logger;
use PhpXmlRpc\Request;
use Psr\Log\LoggerInterface;

/**
 * Cache a pushed payload locally
 */
class PayloadCache
{
    /**
     * Save payloads here
     */
    private const LOG_FILE = 'liss_payloads.log';


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * Constructor.
     */
    public function __construct()
    {
        $path = sprintf(
            '%s/%s',
            \Schoolbox\Config\Loader::getInstance()->get('logs_dir'),
            self::LOG_FILE
        );

        $logFile   = fopen($path, 'a');
        $this->log = new Logger($logFile, $logFile, \Monolog\Logger::DEBUG, [], true);
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

        $this->log->debug($payload);
    }
}
