<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Feed;


use Alaress\Scheduler\Trigger\TriggerInterface;
use Schoolbox\Notif\Message\External\TargetedMessage;
use Schoolbox\Notif\Message\Instance\Instance;
use Schoolbox\Notif\Message\Message;

/**
 * Class Item
 *
 * @deprecated
 * @package Schoolbox\Feed
 */
class Item extends TargetedMessage {

	/**
	 * @deprecated
	 * @param Message $body
	 * @param Instance $instance
	 * @param TriggerInterface $trigger
	 */
	public function __construct($body, $instance, $trigger = null) {
		parent::__construct(null, $body, $instance, $trigger);
	}


} 