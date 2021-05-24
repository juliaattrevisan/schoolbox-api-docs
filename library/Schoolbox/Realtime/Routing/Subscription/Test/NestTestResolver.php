<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription\Test;

use OverflowException;
use Schoolbox\Realtime\Routing\Subscription\Resolver;

class NestTestResolver extends Resolver
{
    public const PATTERN_REPLACE = [ 'INFO.notify.', '*.notify.', '.__debug__', '.#' ];
    public const PATTERN_CHANNEL = 'INFO.notify.%s.__debug__';


    /**
     * {@inheritdoc}
     *
     * @param string $topic
     * @throws OverflowException   if recursion is detected
     * @return array
     */
    public function getSubscribedUserIds($topic)
    {
        return $this->resolve($topic, []);
    }


    /**
     * @param array $seenPaths
     * @return string
     */
    protected function getValue(array $seenPaths)
    {
        return implode(' -> ', array_keys($seenPaths));
    }


    /**
     * @param array $output
     * @return array
     */
    protected function output(array $output)
    {
        return array_map(function ($subscription) {
            return static::trim($subscription);
        }, $output);
    }


    /**
     * @param string $subscription
     * @return string
     */
    public static function trim($subscription)
    {
        return str_replace(static::PATTERN_REPLACE, '', $subscription);
    }
}
