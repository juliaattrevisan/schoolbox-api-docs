<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use GuzzleHttp\Client as HttpClient;
use Predis\ClientInterface as RedisClient;

/**
 * Factory class for the bridge between Atlas calls and their implementation.
 */
class BridgeFactory
{
    /**
     * @var string
     */
    private $token;


    /**
     * @var RedisClient
     */
    private $redisClient;


    /**
     * Constructor
     *
     * @param string      $token
     * @param RedisClient $redisClient
     */
    public function __construct(string $token, RedisClient $redisClient)
    {
        $this->token       = $token;
        $this->redisClient = $redisClient;
    }


    /**
     * Build the bridge class.
     *
     * @return Bridge
     */
    public function build(): Bridge
    {
        return new Bridge(
            new HttpClient([
                'base_uri' => 'https://api.rubicon.com/api/',
                'headers'  => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Accept'        => 'application/json',
                ],
            ]),
            $this->redisClient,
            (new JsonDecoderFactory())->build()
        );
    }
}
