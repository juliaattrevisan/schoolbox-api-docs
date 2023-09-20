<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Request;

use Alaress_Date_Format;
use Schoolbox\API\Token\Jwt;
use Schoolbox\API\Token\JwtParser\SelfIssued;
use Schoolbox\Instance;
use Schoolbox\User;

/**
 * Base class for task daemon message builders.
 */
abstract class BaseMessageFactory
{
    /**
     * Callback URL upon success, if this task invocation has one.
     */
    private ?string $callbackUrl = null;

    /**
     * Callback params upon success, if this task invocation has any.
     */
    private ?array $callbackParams = null;

    /**
     * Callback URL upon failure, if this task invocation has one.
     */
    private ?string $failureCallbackUrl = null;

    /**
     * Callback params upon failure, if this task invocation has any.
     */
    private ?array $failureCallbackParams = null;

    /**
     * The user invoking the task, if there is one.
     */
    private ?User $effector = null;

    /**
     * Constructor
     *
     * @param Instance    $instance     Information about the Schoolbox instance.
     * @param Jwt         $tokenBuilder Used to generate a JWT for use by taskd when calling the callback URL
     */
    public function __construct(
        private Instance $instance,
        private Jwt $tokenBuilder,
    ) {
    }

    /**
     * Set a callback URL for this call to taskd.
     *
     * This URL will be called when taskd completes.
     *
     * @param string|null $callbackUrl
     * @return static
     */
    public function setCallbackUrl(?string $callbackUrl): static
    {
        $clone = clone $this;
        $clone->callbackUrl = $callbackUrl;
        return $clone;
    }

    /**
     * Set parameters to the callback URL for this call to taskd.
     *
     * These parameters will be added to the call to the callback URL when taskd
     * completes.
     *
     * In addition: taskd adds a download link to the uploaded generated ZIP
     * (relative to this Schoolbox instance) in the `href` parameter.
     *
     * @param array|null $callbackParams
     * @return static
     */
    public function setCallbackParams(?array $callbackParams): static
    {
        $clone = clone $this;
        $clone->callbackParams = $callbackParams;
        return $clone;
    }

    /**
     * Set a callback URL for this call to taskd.
     *
     * This URL will be called when taskd fails to complete.
     *
     * @param string|null $failureCallbackUrl
     * @return static
     */
    public function setFailureCallbackUrl(?string $failureCallbackUrl): static
    {
        $clone = clone $this;
        $clone->failureCallbackUrl = $failureCallbackUrl;
        return $clone;
    }

    /**
     * Set parameters to the callback URL for this call to taskd.
     *
     * These parameters will be added to the call to the callback URL when taskd
     * fails to complete.
     *
     * @param array|null $failureCallbackParams
     * @return static
     */
    public function setFailureCallbackParams(?array $failureCallbackParams): static
    {
        $clone = clone $this;
        $clone->failureCallbackParams = $failureCallbackParams;
        return $clone;
    }

    /**
     * Set an effecting user for this call to taskd.
     *
     * This user will be used as the authenticated user for any Schoolbox URLs
     * this task accesses, whether these be:
     * * pages accessed as part of the task's operation
     * * the task's callback URL
     *
     * @param User|null $effector
     * @return static
     */
    public function setUser(?User $effector): static
    {
        $clone = clone $this;
        $clone->effector = $effector;
        return $clone;
    }

    /**
     * Build the request to send to taskd.
     *
     * @return Message
     */
    public function build(): Message
    {
        $token  = $this->effector ? $this->tokenBuilder->generate($this->effector, null, null, [SelfIssued::CUSTOM_CLAIM_TASKD => 1]) : null;
        $params = $this->buildParams();
        if (isset($token)) {
            $params['token'] = $token;
        }
        return new Message(
            $this->getTaskType(),
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

    /**
     * Fetch the type for this task: this will be sent to the task daemon, and
     * used to identify exactly what it will run.
     *
     * @return string
     */
    abstract protected function getTaskType(): string;

    /**
     * Build a set of parameters to this task, from this object's private
     * properties.
     *
     * @return array
     */
    abstract protected function buildParams(): array;
}
