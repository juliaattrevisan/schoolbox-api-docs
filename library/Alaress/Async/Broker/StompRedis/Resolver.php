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

    private bool $acked = false;

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
        if ($this->acked) {
            return;
        }
        try {
            $this->resolver->ack();
            $this->acked = true;
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
        if ($this->acked) {
            return;
        }
        try {
            $this->resolver->nack();
            $this->acked = true;
        } catch (RuntimeException $e) {
            throw new DuplicateResolutionException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
