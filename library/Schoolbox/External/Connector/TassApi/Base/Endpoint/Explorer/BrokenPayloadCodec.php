<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Explorer;

/**
 * Class BrokenPayloadCodec
 *
 * Sometimes we make an endpoint request that dies unexpectedly
 *
 * Capture the endpoint we tried to access, along with the args we supplied
 * and see if we can diagnose this further
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Explorer
 */
class BrokenPayloadCodec
{

    /**
     * Encode a (module, method, params, queryArgs) tuple
     * These represent the api request we tried to make, but it failed for some reason
     *
     * @param array  $queryArgs
     * @return string
     * @throws \JsonException
     */
    public function encode(array $queryArgs): string
    {
        return base64_encode(json_encode($queryArgs, JSON_THROW_ON_ERROR));
    }


    /**
     * Decode an encoded (module, method, params, $queryArgs) tuple
     *
     * @param string $encoded
     * @return array
     * @throws \JsonException
     */
    public function decode(string $encoded): array
    {
        return json_decode(base64_decode($encoded), true, 512, JSON_THROW_ON_ERROR);
    }
}
