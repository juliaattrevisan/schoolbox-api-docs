<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Zenda;

class AccessToken
{
    /**
     * Generates the authentication string
     * At the moment it's a base64 of some config vars concatted, but this might change soon
     *
     * @param string $clientId
     * @param string $clientSecret
     * @return string
     */
    public function generate(string $clientId, string $clientSecret): string
    {
        return base64_encode(sprintf(
            '%s:%s',
            trim($clientId),
            trim($clientSecret)
        ));
    }
}
