<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Custom;

use OAT\Library\Lti1p3Core\Message\Payload\Claim\MessagePayloadClaimInterface;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;

/**
 * Representation of the custom claim in an LTI message.
 *
 * This does not perform variable substitution on custom claims:
 * @see \Schoolbox\LTI\Custom\ClaimFactory for a method for building custom
 *                                         claims which does that.
 *
 * @see https://www.imsglobal.org/spec/lti/v1p3/#custom-properties-and-variable-substitution
 */
class Claim implements MessagePayloadClaimInterface
{
    /**
     * Custom claims, as a set of key-value pairs.
     *
     * @var array<string, string>
     */
    private array $customClaims;

    /**
     * Constructor
     *
     * @param array $customClaims
     */
    public function __construct(array $customClaims)
    {
        $this->customClaims = $customClaims;
    }

    /**
     * @return string
     */
    public static function getClaimName(): string
    {
        return LtiMessagePayloadInterface::CLAIM_LTI_CUSTOM;
    }

    /**
     * @param array $claimData
     * @return Claim
     */
    public static function denormalize(array $claimData): Claim
    {
        return new self($claimData);
    }

    /**
     * @return array|string[]
     */
    public function normalize(): array
    {
        return $this->customClaims;
    }
}
