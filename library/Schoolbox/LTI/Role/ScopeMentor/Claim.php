<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Role\ScopeMentor;

use OAT\Library\Lti1p3Core\Message\Payload\Claim\MessagePayloadClaimInterface;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;

/**
 * Representation of the role-scope mentor claim in an LTI message.
 *
 * @see https://www.imsglobal.org/spec/lti/v1p3/#role-scope-mentor-claims
 */
class Claim implements MessagePayloadClaimInterface
{
    /**
     * A list of user IDs.
     *
     * @var array<string>
     */
    private array $userIds;

    /**
     * Constructor
     *
     * @param string ...$userIds
     */
    public function __construct(string ...$userIds)
    {
        $this->userIds = $userIds;
    }

    /**
     * @return string
     */
    public static function getClaimName(): string
    {
        return LtiMessagePayloadInterface::CLAIM_LTI_ROLE_SCOPE_MENTOR;
    }

    /**
     * @param array $claimData
     * @return Claim
     */
    public static function denormalize(array $claimData): Claim
    {
        return new self(... $claimData);
    }

    /**
     * @return string[]
     */
    public function normalize(): array
    {
        return $this->userIds;
    }
}
