<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\RelyingParty\Generator;

use Ramsey\Uuid\Uuid;

/**
 * Client secret generator for OIDC relying party configurations.
 */
class ClientSecret
{
    /**
     * Generate a random string for client secrets.
     *
     * @return string
     */
    public static function generate(): string
    {
        return Uuid::uuid4()->toString();
    }

    public static function hash(string $clientSecret): string
    {
        return hash('sha256', $clientSecret);
    }
}
