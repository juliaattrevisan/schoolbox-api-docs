<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

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
     * Constructor.
     *
     * @param string $consumerKey
     * @param string $consumerSecret
     */
    public function __construct(
        private string $consumerKey,
        private string $consumerSecret
    ) {
    }


    /**
     * Manufacture a payload to initialise Learnosity javascript app
     *
     * @param string $service
     * @param array  $payload
     * @return string|array
     * @throws \LearnositySdk\Exceptions\ValidationException
     */
    public function initOptions(string $service, array $payload): string|array
    {
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
}
