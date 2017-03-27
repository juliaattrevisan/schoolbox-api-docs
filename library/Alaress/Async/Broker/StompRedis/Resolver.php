<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Async\Broker\StompRedis;

use Alaress\Async\Broker\DuplicateResolutionException;
use Alaress\Async\Broker\IResolver;
use React\Stomp\AckResolver;
use RuntimeException;


/**
 * STOMP-Redis resolver. Conveniently it can just wrap around the STOMP AckResolver and passthru.
 *
 * @package Alaress\Async\Broker\StompRedis
 */
class Resolver implements IResolver
{
    /**
     * @var AckResolver
     */
    private $resolver;


    /**
     * @param AckResolver $resolver
     */
    public function __construct(AckResolver $resolver)
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