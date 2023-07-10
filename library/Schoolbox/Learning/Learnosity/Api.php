<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Alaress\Lumberjack\Logger;
use LearnositySdk\Exceptions\ValidationException;
use LearnositySdk\Request\Init;

/**
 * Generates API calls to Learnosity.
 *
 * FIXME at the moment it just generates initOptions for their
 * embedded javascript app, but might expand to facilitate the
 * other endpoints as needed.
 */
class Api
{
    /**
     * Payloads logged here
     */
    private const LOG_FILE = 'learnosity.log';


    /**
     * Constructor.
     *
     * @param string      $consumerKey
     * @param string      $consumerSecret
     * @param string|null $logDir
     */
    public function __construct(
        private string $consumerKey,
        private string $consumerSecret,
        private ?string $logDir = null
    ) {
    }


    /**
     * Manufacture a payload to initialise Learnosity javascript app
     *
     * @param string $service
     * @param array  $payload
     * @return string|array
     * @throws \JsonException
     * @throws ValidationException
     */
    public function initOptions(string $service, array $payload): string|array
    {
        /* Optionally log our payloads to learnosity
         */
        if (null !== $this->logDir) {
            $logger = $this->spawnLogger();

            $logger->debug(sprintf(
                'Accessed service %s with payload %s',
                $service,
                json_encode($payload, JSON_THROW_ON_ERROR)
            ));
        }

        return (new Init(
            $service,
            [
                'domain'       => $_SERVER['SERVER_NAME'],
                'consumer_key' => $this->consumerKey
            ],
            $this->consumerSecret,
            $payload
        ))->generate();
    }


    /**
     * Spawn a Logger instance, for the lock mechanism
     *
     * @return Logger
     */
    private function spawnLogger(): Logger
    {
        $logFile = sprintf(
            '%s/%s',
            $this->logDir,
            self::LOG_FILE
        );

        $fp = fopen($logFile, 'a+');

        return new Logger($fp, $fp, \Monolog\Logger::DEBUG, [], true);
    }
}
