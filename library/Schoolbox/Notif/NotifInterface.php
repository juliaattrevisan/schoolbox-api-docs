<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Notif;

use Psr\Log\LoggerInterface;

interface NotifInterface extends LoggerInterface
{

    /** Note - If you create any extra topics, ensure they are added to the following for auto create:
     * tools/create-data-dirs.sh
     */
    const TOPIC_SYSTEM = 'sys';           // notifications about top-level events
    const TOPIC_NOTIFICATION = 'notif';   //     "           "   the notification system
    const TOPIC_DATABASE = 'db';          //     "           "   internal database events
    const TOPIC_APPLICATION = 'sb';       //     "           "   Schoolbox application events (ie. Mappers)
    const TOPIC_SYNERGY = 'syn';          //     "           "   synergy database events
    const TOPIC_ADMIN = 'admin';          //     "           "   superuser events (includes assumption)
    const TOPIC_AUTH = 'login';           //     "           "   authentication events
    const TOPIC_CMS = 'cms';              //     "           "   cms events to be logged


    /**
     * @return string
     */
    public function getTopic();


    /**
     * @param string $topic
     * @return self
     */
    public function setTopic($topic);

    /**
     * Logs the given message to the specified channel.
     * Convenience method equivalent to setChannel()->log();
     *
     * @param string $topic
     * @param int    $level
     * @param string $message
     * @param array  $context
     * @return bool
     */
    public function logTo($topic, $level, $message, array $context = array());
}
