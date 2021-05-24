<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Async\Notification\Instance;

use InvalidArgumentException;
use OutOfBoundsException;

/**
 * An interface for builder classes for notification instances
 * (instanceof Instance).
 *
 * @package Alaress\Async\Notification\Instance
 */
interface BuilderInterface
{
    /**
     * Sets the topics for the notification instances that this object builds.
     *
     * @param array $topics
     * @return self
     */
    public function setTopics($topics);

    /**
     * Builds a notification instance given the parameters from this builder.
     *
     * @throws InvalidArgumentException if no topic has been set
     * @throws OutOfBoundsException     if a mandatory attribute has not been set
     * @return Instance
     */
    public function build();
}
