<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Request\DownloadPages;

use Alaress_Date_Format;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Instance;
use Schoolbox\TaskDaemon\Request\Message;
use Schoolbox\User;

/**
 * Factory for building download pages taskd requests.
 */
class MessageFactory
{
    /**
     * The identifier for this task.
     */
    private const TASK = 'download-pages';

    /**
     * Callback URL, if this task invocation has one.
     */
    private ?string $callbackUrl = null;

    /**
     * Callback params, if this task invocation has any.
     */
    private ?array $callbackParams = null;

    /**
     * The user invoking the task, if there is one.
     */
    private ?User $effector = null;

    private array $params = [];

    /**
     * Constructor
     *
     * @param Instance $instance     Information about the Schoolbox instance.
     * @param Jwt      $tokenBuilder Used to generate a JWT for use in the auth header when calling the callback.
     */
    public function __construct(
        private Instance $instance,
        private Jwt $tokenBuilder,
    ) {
    }

    public function withCallbackUrl(?string $callbackUrl): self
    {
        $clone = (clone $this);
        $clone->callbackUrl = $callbackUrl;
        return $clone;
    }

    public function withCallbackParams(?array $callbackParams): self
    {
        $clone = (clone $this);
        $clone->callbackParams = $callbackParams;
        return $clone;
    }

    public function withEffector(?User $effector): self
    {
        $clone = (clone $this);
        $clone->effector = $effector;
        return $clone;
    }

    public function build(string $outfile): Message
    {
        $params = [
            'outfile' => $outfile,
        ];
        $token = $this->effector ? $this->tokenBuilder->generate($this->effector) : null;
        if (isset($token)) {
        }
        return new Message(
            self::TASK,
            $params,
            Alaress_Date_Format::now()->getDateTimeObject(),
            $this->instance->getId(),
            $this->instance->getHostname(),
            $this->instance->getDomain(),
            $this->instance->getVersion(),
            $this->instance->getType(),
            $this->instance->getSchoolCode(),
            $this->callbackUrl,
            $this->callbackParams,
            $token,
        );
    }
}
