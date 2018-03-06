<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

use GuzzleHttp\ClientInterface;
use Schoolbox\Config\TestLoader;
use Schoolbox\Learning\InlineGrader\Kami;

/**
 * Provides testing of Kami Connector that:
 * * does not cost us money to call $$$
 * * does not mutate real data
 */
class MockConnector extends Kami\Connector
{

    public const BASE_URI = 'https://private-anon-0f1d6499dd-kamiembeddingapi.apiary-mock.com/';


    /**
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        parent::__construct($client, 'EXAMPLE_API_KEY', 'TESTING');
    }


    /**
     * Mock connector is always enabled, regardless of API Key configuration.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return true;
    }
}
