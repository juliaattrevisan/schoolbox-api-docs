<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Psr\Log\LoggerInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Url\Factory;
use Schoolbox\UserMapper;

/**
 * Class InstanceIssued
 */
abstract class InstanceIssued extends Base
{
    /**
     * Constructor
     *
     * @param UserMapper        $userMapper
     * @param IConfigLoader     $config
     * @param LoggerInterface   $log
     * @param AudienceMatchMode $matchMode
     */
    public function __construct(
        UserMapper $userMapper,
        IConfigLoader $config,
        LoggerInterface $log,
        private AudienceMatchMode $matchMode,
    ) {
        parent::__construct($userMapper, $config, $log);
    }

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
            if ($this->matchMode === AudienceMatchMode::WARN) {
                $this->log->warning(
                    sprintf(
                        'JWT should contain a "%s" claim: ignoring for now, but this will be rejected in the future',
                        self::CLAIM_AUD,
                    )
                );
                return;
            }
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
            if ($this->matchMode === AudienceMatchMode::WARN) {
                $this->log->warning(
                    sprintf(
                        'JWT "%s" claim should match this instance\'s FQDN: ignoring for now, but this will be rejected in the future',
                        self::CLAIM_AUD,
                    )
                );
                return;
            }
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
        if (!is_scalar($username)) {
            throw new Exception\InvalidIdentity(sprintf('JWT "%s" claim must be a scalar value', $usernameClaim));
        }
        $username = trim((string)$username);
        if ($username === '') {
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
