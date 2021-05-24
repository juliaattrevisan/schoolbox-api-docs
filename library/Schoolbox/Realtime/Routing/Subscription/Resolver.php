<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription;

use Alaress\Routing\IRouter;
use InvalidArgumentException;
use OverflowException;

/**
 * Can recursively resolve Trie paths to obtain a list of subscribed users
 *
 * @package Schoolbox\Realtime\Routing\Subscription
 */
class Resolver
{
    /**
     * @var IRouter
     */
    private $router;


    /**
     * Resolver constructor.
     *
     * @param IRouter $router
     */
    public function __construct(IRouter $router)
    {
        $this->router = $router;
    }


    /**
     * Recursively burrow through redirect topics and compile a list of users ultimately subscribed to the
     * given topic, whether directly or by proxy.
     *
     * @param string $topic
     * @throws OverflowException   if recursion is detected
     * @return int[]
     */
    public function getSubscribedUserIds($topic)
    {
        return array_keys($this->resolve($topic, []));
    }


    /**
     * Internal recursive function
     *
     * @param string $topic
     * @param array  $seenPaths cache of paths seen so far in this traversal in order to protect against recursion
     * @return int[]
     * @throws OverflowException
     */
    protected function resolve($topic, array $seenPaths)
    {
        if (isset($seenPaths[$topic])) {
            throw new OverflowException('Recursion detected: ' . implode(' -> ', array_keys($seenPaths)) . " -> $topic");
        }

        try {
            $data = array_filter($this->router->get($topic));

            $seenPaths[$topic] = true;

            $userIds = [];

            // define this value before we get into the loop, for efficiency
            $payload = $this->getValue($seenPaths);

            foreach ($data as $key => $value) {
                if (is_int($key)) {
                    // treat integers as user IDs
                    $userIds[$key] = $payload;
                } else {
                    // treat strings as a redirect path
                    foreach ($this->resolve($key, $seenPaths) as $userId => $payload) {
                        $userIds[$userId] = $userIds[$userId] ?? $payload;
                    }
                }
            }

            return $this->output($userIds);
        } catch (InvalidArgumentException $e) {
            // nobody subscribed, and that's totally ok
            return [];
        }
    }


    /**
     * @param array $seenPaths
     * @return bool
     */
    protected function getValue(array $seenPaths)
    {
        return true;
    }


    /**
     * @param array $output
     * @return array
     */
    protected function output(array $output)
    {
        return $output;
    }


    /**
     * @return IRouter
     */
    public function getRouter()
    {
        return $this->router;
    }
}
