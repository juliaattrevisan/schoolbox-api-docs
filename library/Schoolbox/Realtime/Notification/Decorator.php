<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Schoolbox\Realtime\Notification;


use Alaress\Async\Notification\Notification;
use Alaress\Async\Notification\NotificationBundle;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use TextFormatter;

class Decorator extends NotificationBundle {

	/**
	 * How many params can be appended before they get slid under "and n others"
	 */
	const STACK_LIMIT = 3;


	/**
	 * @param Notification $notification
	 */
	public function setNotification($notification) {
		$this->notifications = array();
		$this->appendNotification($notification);
	}


	/**
	 * @return array
	 */
	protected function toArray() {
		$array = array();

		foreach($this->getAll() as $notification) {
			$hash = $notification->getId();

			$array[$hash] = array(
				'title' => array(
					'text' => $notification->getTitle(),
					'params' => $this->renderParametersAsArray($notification),
				),
				'namespace' => $notification->getNamespace(),
				'icon' => $notification->getIcon(),
				'href' => $notification->getHref(),
				'meta' => $notification->getMeta(),
				'time' => $notification->getTimestamp()
			);
		}

		return $array;
	}

	/**
	 * Render the stored array of notification instances as a Twig context array (for sending via email)
	 *
	 * @param array $coreContext - the base context of the template to be rendered, if any
	 * @return array             - a context Twig can parse
	 */
	public function renderAsTwigContext($coreContext = array()) {
		$context = $this->toArray();
		$fqdn = isset($coreContext['config']['fqdn']) ? 'http://'.$coreContext['config']['fqdn'] : '';

		foreach($context as $id => $n) {
			// icon cannot be a protected link
			if(!strlen($n['icon']) || strpos($n['icon'], '/portrait') === 0) {
				// change to be the generic icon for the namespace
				$context[$id]['icon'] = '/images/icon.php?size=48&icon='.$n['namespace'];
			}

			foreach(array('icon', 'href') as $field) {
				// leading slash needs FQDN prepended
				if($n[$field][0] == '/') {
					$context[$id][$field] = $fqdn . $n[$field];
				}
			}
		}

		return $context;
	}


	/**
	 * SitePage is dead. Long live SitePage.
	 *
	 * @deprecated
	 * @return array
	 */
	public function renderAsSitePageArray() {
		$context = $this->toArray();

		foreach($this->notifications as $n) {
			// compile the title
			$context[$n->getId()]['title'] = $this->renderTitleAsHtml($n);

			// convert the time into human readable
			$date = new \Alaress_Date_Format($n->getTimestamp(), 'U');
			$context[$n->getId()]['longtime'] = $date->getLong();
			$context[$n->getId()]['timeonly'] = $date->getUsingFormat('g:ia');
		}

		return $context;
	}


	/**
	 * Render the stored array of notification instances as an array that can be sent down a websocket
	 *
	 * @param bool $asUnread - by default, the message will be marked as coming from the 'read' context
	 * @return array
	 */
	public function renderAsSocketArray($asUnread = false) {
		$result = array();

		foreach($this->getAll() as $notification) {
			$id = $notification->getId();
			$result[$id] = array(
				'id'        => $id,
				'body'      => $notification->getTitle(),
				'params'    => $this->renderParametersAsArray($notification),
				'namespace' => $notification->getNamespace(),
				'timestamp' => $notification->getTimestamp(),
				'status'    => $asUnread ? 'unread' : 'read'
			);
			if($body = $notification->getMeta('body')) {
				$result[$id]['abstract'] = TextFormatter::cutSentence($body, 48);
			}
		}

		return $result;
	}


	/**
	 * @param Notification $notification
	 * @return array
	 */
	public function renderParametersAsArray($notification) {
		$array = array();
		foreach($notification->getParameters() as $token => $params) {
			/** @var ParameterInterface[] $params */
			foreach($params as $param) {
				$array[$token][] = $param->jsonSerialize();
			}
		}
		return $array;
	}


	/**
	 * Translate a the :tokenised title of a notification into an HTML string
	 *
	 * @param Notification $notification
	 * @return string
	 */
	public function renderTitleAsHtml($notification) {
		$text = $notification->getTitle();

		foreach($notification->getParameters() as $token => $params) {
			/** @var ParameterInterface[] $params */

			// sort params by reverse timestamp to give precedence to most recent
			krsort($params);

			// probably a bit of a code smell, but retrieves first value.
			// equivalent to $params[0] but where the keys are unknown
			$subst = $this->param2html(reset($params));

			/** @var ParameterInterface[] $stack */
			$stack = array();

			foreach($params as $param) {
				switch($param->getMode()) {
					case ParameterInterface::STACKING_REPLACE:
						$subst = $this->param2html($param);
						break;
					case ParameterInterface::STACKING_IGNORE:
						// ignore? ok, ignored.
						continue;
					case ParameterInterface::STACKING_APPEND:
						$stack[] = $param;
						break;
				}
			}

			// use the stack if it was populated
			if(count($stack)) {
				$subst = $this->stack2html($stack);
			}

			$text = str_replace(":$token", $subst, $text);
		}

		return $text;
	}


	/**
	 * Translate a parameter into an HTML anchor tag
	 *
	 * @param ParameterInterface $param
	 * @return string
	 */
	protected function param2html($param) {
		if($param->getHref()) {
			return sprintf('<a href="%s">%s</a>', $param->getHref(), $param->getText());
		}
		return "<a>{$param->getText()}</a>";
	}


	/**
	 * Translate an array of parameters into "x, y, and n others..."
	 *
	 * @param ParameterInterface[] $params
	 * @return string
	 */
	protected function stack2html($params) {
		$bits = array();

		/** @var ParameterInterface[] $params */
		$params = array_unique($params, SORT_STRING);

		// take the first n params, and tuck the rest away
		for($i = 0; $i < self::STACK_LIMIT - 1; ++$i) {
			if(isset($params[0])) {
				$bits[] = $this->param2html(array_shift($params));
			}
		}

		switch(count($params)) {
			case 0:
				// do nothing
				break;
			case 1:
				// if it's just the one, include it
				$bits[] = $this->param2html($params[0]);
				break;
			default:
				// if there's anything more left over, aggregate it
				$others = array();
				foreach($params as $p) {
					$others[] = $p->getText();
				}
				$bits[] = sprintf('<a data-others="%s">%s others</a>', implode(';',$others), count($params));
				break;
		}

		if(count($bits) >= 2) {
			$parts = array(
				implode(', ', array_slice($bits, 0, count($bits) - 1)),
				$bits[count($bits) - 1]
			);
		}
		else {
			$parts = $bits;
		}

		return implode(' and ', $parts);
	}


} 