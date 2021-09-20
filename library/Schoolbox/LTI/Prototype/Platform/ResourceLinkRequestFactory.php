<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype\Platform;

use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\Launch\Builder\PlatformOriginatingLaunchBuilder;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Role\Type\ContextRole;
use OAT\Library\Lti1p3Core\Role\Type\SystemRole;
use Schoolbox\UserMapper;

/**
 * Builder for resource link requests in the prototype.
 */
class ResourceLinkRequestFactory
{
    // The various roles which the current user may be claimed to be.
    // If the LTI tool finds that the authenticated user isn't one of these
    // roles, it should exit out.
    private const CONTEXT_ROLE_INSTRUCTOR = 'Instructor';
    private const CONTEXT_ROLE_LEARNER = 'Learner';
    private const SYSTEM_ROLE_NONE = 'None';

    /**
     * User mapper: used for finding the user to build a resource link request
     * for.
     */
    private UserMapper $userMapper;

    /**
     * The LTI registration to build the resource link request for.
     */
    private RegistrationInterface $registration;

    /**
     * Constructor
     *
     * @param UserMapper            $userMapper
     * @param RegistrationInterface $registration
     */
    public function __construct(UserMapper $userMapper, RegistrationInterface $registration)
    {
        $this->userMapper   = $userMapper;
        $this->registration = $registration;
    }

    /**
     * Build the message.
     *
     * @param string $loginHint
     * @param string $targetLinkUri
     * @return LtiMessageInterface
     * @throws LtiExceptionInterface if something goes wrong building message
     */
    public function build(string $loginHint, string $targetLinkUri): LtiMessageInterface
    {
        // Figure out who's trying to log in: use that to determine their role
        $role = null;
        if (strlen($loginHint) > 0) {
            $user = $this->userMapper->findByUsername($loginHint);
            if ($user) {
                $role = $user->getRole()->isStaff()
                    ? new ContextRole(self::CONTEXT_ROLE_INSTRUCTOR)
                    : new ContextRole(self::CONTEXT_ROLE_LEARNER);
            }
        }
        $role ??= new SystemRole(self::SYSTEM_ROLE_NONE);

        $builder = new PlatformOriginatingLaunchBuilder();
        return $builder->buildPlatformOriginatingLaunch(
            $this->registration,
            LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST,
            $targetLinkUri,
            $loginHint,
            null,
            [$role],
            [new ResourceLinkClaim('test', 'Test resource link'),],
        );
    }
}
