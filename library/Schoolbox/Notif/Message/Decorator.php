<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message;


use Alaress_Date_Format;
use Schoolbox\Notif\Message\External\TargetedMessage;
use Schoolbox\Notif\MQ\Connector;

/**
 * Helper class to assist with extracting information in an easily-digestible way from a TargetedMessage
 *
 * Class Decorator
 * @package Schoolbox\Notif\Message
 */
class Decorator {

	/** @var TargetedMessage */
	protected $message;


	/**
	 * @param TargetedMessage $targetedMessage
	 */
	public function __construct($targetedMessage) {
		if($targetedMessage instanceof TargetedMessage) {
			$this->setMessage($targetedMessage);
		}
	}


	/**
	 * @param TargetedMessage $targetedMessage
	 * @return self
	 */
	public function setMessage($targetedMessage) {
		$this->message = $targetedMessage;
		return $this;
	}


	/**
	 * @return Alaress_Date_Format
	 */
	public function getAlaressDateFormat() {
		return new Alaress_Date_Format($this->message->getTimestamp(), 'U');
	}


	/**
	 * @return string
	 */
	public function formatHtml() {
		return $this->formatMarkup(
			function($href, $text) {
				return sprintf('<a href="%s">%s</a>', $href, $text);
			},
			function($text) {
				return sprintf('<a>%s</a>', $text);
			}
		);
	}


	/**
	 * @param string $uriBase
	 * @return string
	 */
	public function formatMail($uriBase) {
		return $this->formatMarkup(
			function($href, $text) use ($uriBase) {
				return sprintf('<a href="%s%s">%s</a>', $uriBase, $href, $text);
			},
			function($text) {
				return sprintf('<a>%s</a>', $text);
			}
		);
	}


	/**
	 * @return string
	 */
	public function formatPlain() {
		return $this->formatMarkup(
			function($text) {
				return $text;
			},
			function($text) {
				return $text;
			}
		);
	}


	/**
	 * Base method for turning a message into a string of markup
	 *
	 * @param callable $hrefCallback
	 * @param callable $noHrefCallback
	 * @return string
	 */
	public function formatMarkup($hrefCallback, $noHrefCallback) {
		$params = $this->message->getInstance()->getParams();
		$msgString = $this->message->getBody()->getMessageString();

		// no params, raw text
		if(!(is_array($params) && count($params))) {
			return $msgString;
		}

		foreach($params as $token => $value) {
			// text and href
			if(is_array($value) && count($value) == 2) {
				$value = array_values($value);
				$a = call_user_func($hrefCallback, $value[1], $value[0]);
			}
			// just text
			elseif(is_string($value)) {
				$a = call_user_func($noHrefCallback, $value);
			}
			// nothing we recognise: skip dat!
			else {
				continue;
			}

			$match = ":$token";
			$msgString = str_replace($match, $a, $msgString);
		}

		return $msgString;
	}


	/**
	 * @param bool $unread
	 * @return string
	 */
	public function formatJson($unread = false) {
		return json_encode($this->formatArray($unread));
	}


	/**
	 * @param bool $unread
	 * @return array
	 */
	public function formatArray($unread = false) {
		return array(
			'body'      => $this->message->getBody()->toArray(),
			'params'    => $this->message->getInstance()->getParams(),
			'timestamp' => $this->message->getTimestamp(),
			'status'    => $unread ? 'unread' : 'read'
		);
	}

	/**
	 * Formats the message appropriate for Twig context. Uses strategies based on the message namespace.
	 *
	 * @param array $coreContext
	 * @return array
	 */
	public function formatContext($coreContext) {

		// consolidate assessable namespaces
		if(in_array($this->message->getNamespace(), array('duework', 'assess'))) {
			$ns = 'work';
		}
		else {
			$ns = $this->message->getNamespace();
		}

		$href = $this->getHref();
		$icon = $this->getIcon();

		// icon cannot be a protected link
		if(!strlen($icon) || strpos($icon, '/portrait') === 0) {
			// change to be the generic icon for the namespace
			$icon = '/images/icon.php?size=48&icon='.$ns;
		}

		$msgArray = array(
			'pretty'    => $this->formatMail("http://".$coreContext['config']['fqdn']),
			'href'      => $href ? ("http://".$coreContext['config']['fqdn'] . $href) : '',
			'icon'      => $icon ? ("http://".$coreContext['config']['fqdn'] . $icon) : '',
			'timestamp' => $this->message->getTimestamp(),
			'meta'      => $this->message->getBody()->getMeta(),
		);

		// cast time to day
		$day = ((int)floor($msgArray['timestamp'] * 0.000011574)) * 86400;


		switch($ns) {
			case 'work':
				// don't use numeric keys (recursive merge does it wrong), but group by day
				$context['groups'][$ns]["dates"]["d-$day"][Connector::deriveMessageId($this->message)] = $msgArray;
				break;
			default:
				$context['groups'][$ns]['messages'][Connector::deriveMessageId($this->message)] = $msgArray;
				break;
		}

		return $context;
	}

	/**
	 * @return string
	 */
	public function getHref() {
		$array = $this->message->getBody()->toArray();
		return $array['href'];
	}


	/**
	 * @return string
	 */
	public function getIcon() {
		$array = $this->message->getBody()->toArray();
		return $array['icon'];
	}

}