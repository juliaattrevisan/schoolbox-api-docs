<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Mail;


use Schoolbox\IConfigLoader;
use Swift_Transport;

interface IMailer {

	/**
	 * @param IConfigLoader $conf
	 * @param bool $reinitialise
	 * @return Swift_Transport
	 */
	public static function getConfiguredSwiftTransport($conf, $reinitialise = false);

	/**
	 * @param bool $reinitialise
	 * @return Swift_Transport
	 */
	public static function getSwiftTransport($reinitialise = false);

	/**
	 * @param string $string
	 * @return array
	 */
	public static function parseAddressString($string);

	/**
	 * @param string $address
	 * @param string $name
	 * @return string
	 */
	public static function buildAddress($address, $name = '');


	public function __construct($template, $subject = "", $to = "", $host = "");

	public function setFrom($email, $name = '');

	public function setReplyTo($email, $name = '');

	public function setFromAsCurrentUser();

	public function setTo($to);

	public function setSubject($subject);

	public function addFile($file, $mime = 'text/plain', $filename = '', $isFile = true);

	public function addImage($file, $type='appplication/octet');

	public function send($to = '');

}
