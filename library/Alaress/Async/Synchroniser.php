<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
        while ($data = $this->redis->rpop($this->key)) {
            $callback($data);
        }
        $done();
    }

}
