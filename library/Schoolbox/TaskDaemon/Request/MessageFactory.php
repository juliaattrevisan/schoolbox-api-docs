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

/**
 * Factory for building request messages.
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
     */
    public function __construct(Instance $instance, Jwt $tokenBuilder)
    {
        $this->instance     = $instance;
        $this->tokenBuilder = $tokenBuilder;
    }

    /**
     * Build a message.
     *
     * @param string      $task
     * @param array       $params
     * @param string|null $callbackUrl
     * @param array|null  $callbackParams
     * @param User|null   $effector
     * @return Message
     */
    public function build(
        string $task,
        array $params,
        ?string $callbackUrl,
        ?array $callbackParams,
        ?User $effector
    ): Message {
        // Convert the instance to an intermediary format
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
            $effector ? $this->tokenBuilder->generate($effector) : null,
        );
    }
}
