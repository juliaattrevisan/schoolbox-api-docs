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
use OAT\Library\Lti1p3Core\Role\RoleInterface;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Builder for messages from the prototype.
 */
class MessageFactory
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
     * @param string $messageType
     * @return LtiMessageInterface
     * @throws LtiExceptionInterface if something goes wrong building message
     */
    public function build(string $loginHint, string $messageType): LtiMessageInterface
    {
        // Figure out who's trying to log in: use that to determine their role
        $role = null;
        if (strlen($loginHint) > 0) {
            $user = $this->userMapper->findByUsername($loginHint);
            if ($user) {
                $role = $user->getRole()->isStaff()
                    ? RoleInterface::NAMESPACE_CONTEXT . self::CONTEXT_ROLE_INSTRUCTOR
                    : RoleInterface::NAMESPACE_CONTEXT . self::CONTEXT_ROLE_LEARNER
                ;
            }
        }
        $role ??= RoleInterface::NAMESPACE_SYSTEM . self::SYSTEM_ROLE_NONE;
        switch ($messageType) {
            case LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST:
                $targetLinkUrl = $this->registration->getTool()->getLaunchUrl();
                break;
            case LtiMessageInterface::LTI_MESSAGE_TYPE_DEEP_LINKING_REQUEST:
                $targetLinkUrl = $this->registration->getTool()->getDeepLinkingUrl();
                break;
            default:
                throw new UnexpectedValueException('Unrecognised message type');
        }

        $builder = new PlatformOriginatingLaunchBuilder();
        return $builder->buildPlatformOriginatingLaunch(
            $this->registration,
            $messageType,
            $targetLinkUrl,
            $loginHint,
            null,
            [$role],
            [new ResourceLinkClaim('test', 'Test message'),],
        );
    }
}
