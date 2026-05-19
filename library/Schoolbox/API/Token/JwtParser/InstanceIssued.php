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
     * Validate the audience of the JWT.
     *
     * For an instance-issued JWT, the audience must match the current hostname.
     *
     * @param array $claims The JWT's claims, once decoded.
     */
    protected function assertValidAudience(array $claims): void
    {
        if (!isset($claims[self::CLAIM_AUD])) {
            throw new Exception\Rejected(sprintf('JWT must contain a "%s" claim', self::CLAIM_AUD));
        }
        $audience      = (array)$claims[self::CLAIM_AUD];
        $audienceMatch = false;
        $hostname      = $this->determineHostname();
        foreach ($audience as $audienceValue) {
            if ($hostname === $audienceValue) {
                $audienceMatch = true;
                break;
            }
        }
        if (!$audienceMatch) {
            throw new Exception\Rejected(sprintf('JWT "%s" claim must match this instance\'s FQDN', self::CLAIM_AUD));
        }
    }

    /**
     * Extract the Schoolbox username of the user to authorize for this JWT.
     *
     * @param array  $claims        The JWT's claims, once decoded.
     * @param string $usernameClaim The claim which contains the username.
     * @return string
     */
    protected function extractUsername(array $claims, string $usernameClaim): string
    {
        if (!isset($claims[$usernameClaim])) {
            throw new Exception\InvalidIdentity(sprintf('JWT must contain a "%s" claim', $usernameClaim));
        }
        $username = $claims[$usernameClaim];
        if (trim($username) === '') {
            throw new Exception\InvalidIdentity(sprintf('JWT "%s" claim must not be empty', $usernameClaim));
        }
        return $username;
    }

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
