<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Schoolbox\Notif\Message\External;


use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use InvalidArgumentException;

/**
 * Class CompatibleMessage
 *
 * @deprecated this is purely for BC with TargetedMessage
 * @package Schoolbox\Notif\Message\External
 */
class TargetedConverter {

	/**
	 * Attempt to convert unknown data into a notification Instance
	 *
	 * @param string|array|TargetedMessage $mixed
	 * @throws InvalidArgumentException
	 * @return Instance
	 */
	public function convert($mixed) {
		if($mixed instanceof Instance) {
			return $mixed;
		}
		try {
			if($mixed instanceof TargetedMessage) {
				$tmsg = $mixed;
			}
			else {
				// could not json_decode the message, attempt to cast an old TargetedMessage
				$tmsg = @unserialize($mixed);

				if(!$tmsg instanceof TargetedMessage) {
					throw new InvalidArgumentException("Conversion not from a TargetedMessage");
				}
			}

			$body = $tmsg->getBody();
			$inst = $tmsg->getInstance();

			$params = array();
			foreach($inst->getParams() as $token => $param) {
				if(is_array($param)) {
					$param[$token] = new Param($param[0], $param[1]);
				}
				else {
					$param[$token] = new Param($param);
				}
			}

			return new Instance(
				$body->getMessageString(), $tmsg->getNamespace(), $body->getIcon(), $body->getHref(),
				$params, $inst->getSeverity(), $inst->getTopics(), $tmsg->getTimestamp(), $body->getMeta(),
				$inst->getContext()
			);

		}
		catch(InvalidArgumentException $e) {
			if(is_array($mixed)) {
				$mixed = json_encode($mixed);
			}
			return Instance::jsonUnserialize($mixed);
		}
	}


} 