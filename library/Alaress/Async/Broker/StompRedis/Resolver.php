<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Broker\StompRedis;

use Alaress\Async\Broker\DuplicateResolutionException;
use Alaress\Async\Broker\IResolver;
use Alaress\Async\Stomp\IAckResolver;
use RuntimeException;

/**
 * STOMP-Redis resolver. Conveniently it can just wrap around the STOMP AckResolver and passthru.
 *
 * @package Alaress\Async\Broker\StompRedis
 */
class Resolver implements IResolver
{
    /**
     * @var IAckResolver
     */
    private $resolver;


    /**
     * @param IAckResolver $resolver
     */
    public function __construct(IAckResolver $resolver)
    {
        $this->resolver = $resolver;
    }


    /**
     * @throws DuplicateResolutionException
     * @return void
     */
    public function acknowledge()
    {
        try {
            $this->resolver->ack();
        } catch (RuntimeException $e) {
            throw new DuplicateResolutionException($e->getMessage(), $e->getCode(), $e);
        }
    }


    /**
     * @throws DuplicateResolutionException
     * @return void
     */
    public function reject()
    {
        try {
            $this->resolver->nack();
        } catch (RuntimeException $e) {
            throw new DuplicateResolutionException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
