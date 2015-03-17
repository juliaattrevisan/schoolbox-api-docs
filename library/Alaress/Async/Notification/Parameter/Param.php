<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Parameter;
use Alaress\Json;

/**
 * This class is designed to be overridden into a collection of specialised param types:
 * eg. UserParam, CategoryParam, etc.
 *
 * Class Param
 * @package Alaress\Async\Notification
 */
class Param implements ParameterInterface {

	/** @var string */
	protected $text;

	/** @var string */
	protected $href;

	/** @var int */
	protected $mode;


	/**
	 * @param string $text
	 * @param string $href
	 * @param int $stacking
	 */
	public function __construct($text, $href = null, $stacking = self::STACKING_REPLACE) {
		$this->text = $text;
		$this->href = $href;
		$this->mode = $stacking;
	}


	/**
	 * @return string
	 */
	public function getHref() {
		return $this->href;
	}


	/**
	 * @return int
	 */
	public function getMode() {
		return $this->mode;
	}


	/**
	 * @return string
	 */
	public function getText() {
		return $this->text;
	}


	/**
	 * (PHP 5 &gt;= 5.4.0)<br/>
	 * Specify data which should be serialized to JSON
	 *
	 * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return mixed data which can be serialized by <b>json_encode</b>,
	 *       which is a value of any type other than a resource.
	 */
	public function jsonSerialize() {
		return array(
			'text' => $this->text,
			'href' => $this->href,
			'mode' => $this->mode
		);
	}


	/**
	 * Flatten this notification instance
	 *
	 * @return string
	 */
	public function __toString() {
		return json_encode($this);
	}


}