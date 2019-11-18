<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async;

use Predis\ClientInterface;


class Synchroniser
{

    /** @var ClientInterface */
    private $redis;

    /** @var string */
    private $key;


    /**
     * @param object          $context
     * @param ClientInterface $redis
     */
    public function __construct($context, ClientInterface $redis)
    {
        $this->key   = 'sb:cb:'.sha1(get_class($context));
        $this->redis = $redis;
    }


    /**
     * @param string|string[] $data
     * @param callable        $callback Call with every value of $data
     * @param callable        $done     Call once $callback is finished
     */
    public function synchronise($data, callable $callback, callable $done)
    {
        $busy = $this->redis->exists($this->key);

        // if the data given is an array, conveniently it will queue all the values separately for us
        $this->redis->lpush($this->key, (array)$data);

        if ($busy) {
            // someone else is dealing with this, but you've deposited your payload
            // so you can just go back to whatever else you were doing
            return;
        }

        // lock obtained - you are the worker: sozlol, you do everyone's work
        $this->flush($callback, $done);
    }


    /**
     * @param callable $callback
     * @param callable $done
     */
    public function flush(callable $callback, callable $done)
    {
        while ($data = $this->redis->rpop($this->key)) {
            $callback($data);
        }
        $this->redis->del([$this->key]);
        $done();
    }
}
