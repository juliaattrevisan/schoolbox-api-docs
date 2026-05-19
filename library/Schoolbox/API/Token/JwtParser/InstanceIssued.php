<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Schoolbox\Url\Factory;

/**
 * Class InstanceIssued
 */
abstract class InstanceIssued extends Base
{
    /**
     * Determine what this instance's hostname is.
     *
     * This is used for:
     * * matching the JWT's `iss` claim, when determining if it's self-issued
     * * matching against the JWT's `aud` claims, when determining if it's valid
     *   for this instance
     *
     * @return string
     */
    protected function determineHostname(): string
    {
        return new Factory($this->config)->buildFullyQualified()->getHost();
    }
}
