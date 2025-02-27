<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Request;

use Alaress_Date_Format;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Instance;
use Schoolbox\User;
use Schoolbox\User\Token\Mapper as TokenMapper;

/**
 * Factory for building request messages.
 *
 * Most use-cases for the task daemon should extend `BaseMessageFactory`
 * instead: that class provides convenience methods for the basic task daemon
 * parameters, and also allows override for building parameters for each task
 * type.
 */
class MessageFactory
{
    /**
     * Information about the Schoolbox instance.
     */
    private Instance $instance;

    /**
     * Token builder: used to generate a JWT for use in the auth header when
     * calling back.
     *
     * TODO is this really necessary? Maybe a shared secret would be enough?
     *      The shared secret also wouldn't be an auth token for the user.
     */
    private Jwt $tokenBuilder;

    /**
     * Constructor
     *
     * @param Instance $instance
     * @param Jwt      $tokenBuilder
     * @param TokenMapper $tokenMapper Used to generate a token for the effector
     */
    public function __construct(
        Instance $instance,
        Jwt $tokenBuilder,
        private TokenMapper $tokenMapper
    ) {
        $this->instance     = $instance;
        $this->tokenBuilder = $tokenBuilder;
    }

    /**
     * Build a message.
     *
     * @param string                   $task
     * @param array                    $params
     * @param string|null              $callbackUrl
     * @param array|null               $callbackParams
     * @param string|null              $failureCallbackUrl
     * @param array|null               $failureCallbackParams
     * @param User|null                $effector
     * @param Alaress_Date_Format|null $tokenExpiry
     * @return Message
     */
    public function build(
        string $task,
        array $params,
        ?string $callbackUrl,
        ?array $callbackParams,
        ?string $failureCallbackUrl,
        ?array $failureCallbackParams,
        ?User $effector,
        ?Alaress_Date_Format $tokenExpiry = null,
    ): Message {
        /* if no effector, use the system user */
        if (!$effector) {
            $effector = new User();
            $effector->setId(1);
            $effector->setUsername(User::USERNAME_SCHOOLBOX_ADMIN);
        }
        $tokenUser = $this->tokenMapper->generateForUser($effector, $effector, \Alaress_Date_Format::now()->addSeconds(JWT::API_JWT_TIMEOUT));
        // Convert the instance to an intermediary format
        $token = $this->tokenBuilder->generate($effector, $tokenUser->getJwtId(), $tokenExpiry);
        return new Message(
            $task,
            $params,
            Alaress_Date_Format::now()->getDateTimeObject(),
            $this->instance->getId(),
            $this->instance->getHostname(),
            $this->instance->getDomain(),
            $this->instance->getVersion(),
            $this->instance->getType(),
            $this->instance->getSchoolCode(),
            $callbackUrl,
            $callbackParams,
            $token,
            $failureCallbackUrl,
            $failureCallbackParams,
            $token,
        );
    }
}
