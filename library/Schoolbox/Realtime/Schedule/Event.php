<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Schedule;

/**
 * @deprecated this exists purely so existing rabbit data won't explode. Safe to remove 17.5+
 * @package Schoolbox\Realtime\Schedule
 */
class Event extends Event\Event
{
    // retained for backward compatibility of existing triggers held serialized in rabbit queues
}