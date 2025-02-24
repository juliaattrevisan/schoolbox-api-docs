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
use Schoolbox\User\Token\Mapper as TokenMapper;

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
     * The expiry of the login token created for `$effector`, if there is one.
     */
    private ?Alaress_Date_Format $tokenExpiry = null;

    /**
     * Constructor
     *
     * @param Instance    $instance     Information about the Schoolbox instance.
     * @param Jwt         $tokenBuilder Used to generate a JWT for use by taskd when calling the callback URL
     * @param TokenMapper $tokenMapper  Used to generate a token for the effector
     */
    public function __construct(
        private Instance $instance,
        private Jwt $tokenBuilder,
        private TokenMapper $tokenMapper
    ) {
    }

    /**
     * Set a fully-qualified callback URL for this call to taskd.
     *
     * This URL will be called via POST when taskd completes,
     * using the callbackParams specified as a POST payload.
     *
     * If you use our aura router to generate urls, use the following
     * example to apply a fully-qualified name to it:
     *
     *     // an instance of (new Factory($this->getConfigLoader()))->buildFullyQualified()
     *     private UriInterface $baseUrl;
     *
     *     (string)$this->baseUrl->withPath($callbackUrl)
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
     * Set POST parameters to the callback URL for this call to taskd.
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
     * Set the expiry time of the token for `$this->effector`.
     *
     * The token created for them won't be valid after this date.
     *
     * @param Alaress_Date_Format|null $tokenExpiry
     * @return $this
     */
    public function setTokenExpiry(?Alaress_Date_Format $tokenExpiry): static
    {
        $clone = clone $this;
        $clone->tokenExpiry = $tokenExpiry;
        return $clone;
    }

    /**
     * Build the request to send to taskd.
     *
     * @return Message
     */
    public function build(): Message
    {
        $effector = $this->effector;
        $tokenUser = $this->tokenMapper->generateForUser($effector, $effector, $this->tokenExpiry);
        $token  = $this->effector
            ? $this->tokenBuilder->generate(
                $this->effector,
                $tokenUser->getJwtId(),
                $this->tokenExpiry,
                [SelfIssued::CUSTOM_CLAIM_TASKD => 1],
            )
            : null;
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
            $this->failureCallbackUrl,
            $this->failureCallbackParams,
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
