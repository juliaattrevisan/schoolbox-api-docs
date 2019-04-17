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
use Psr\Log\LoggerInterface;
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
	 * @return array ['valid' => ['email' => 'name'], 'invalid' => ['email' => 'name']]
	 */
	public static function parseAddressString($string): ?array;

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

	public function addImage($file, $type='application/octet');

	public function send($to = '');

	public function setLogger(LoggerInterface $logger);

	public function encodeBase64();

}
