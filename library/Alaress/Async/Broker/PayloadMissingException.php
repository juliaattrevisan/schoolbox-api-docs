<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Broker;

use Alaress\Async\Broker\StompRedis\Resolver;
use OutOfBoundsException;
use Throwable;

class PayloadMissingException extends OutOfBoundsException implements IBrokerException
{

    /** @var Resolver */
    private $resolver;

    /**
     * Construct the exception. Note: The message is NOT binary safe.
     *
     * @param string    $key
     * @param Resolver  $resolver
     * @param int       $code     [optional] The Exception code.
     * @param Throwable $previous [optional] The previous throwable used for the exception chaining.
     */
    public function __construct($key, Resolver $resolver, $code = 0, Throwable $previous = null)
    {
        parent::__construct("No payload for `{$key}` exists", $code, $previous);
        $this->resolver = $resolver;
    }


    /**
     * @return Resolver
     */
    public function getResolver()
    {
        return $this->resolver;
    }
}
