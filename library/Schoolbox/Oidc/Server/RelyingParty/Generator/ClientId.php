<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\RelyingParty\Generator;

use Ramsey\Uuid\Uuid;

/**
 * Client ID generator for OIDC relying party configurations.
 */
class ClientId
{
    /**
     * Generate a random string for client IDs.
     *
     * @return string
     */
    public static function generate(): string
    {
        return Uuid::uuid4()->toString();
    }
}
