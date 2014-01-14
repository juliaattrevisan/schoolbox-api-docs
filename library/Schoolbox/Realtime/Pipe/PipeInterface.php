<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;


use Ratchet\ConnectionInterface;
use Schoolbox\User;

interface PipeInterface {

	/**
	 * @param ConnectionInterface $conn
	 */
	public function __construct($conn);

	/**
	 * @return void
	 */
	public function close();

	/**
	 * @return string
	 */
	public function getRemoteAddress();

	/**
	 * @return string
	 */
	public function getSession();

	/**
	 * @return string
	 */
	public function getSocketKey();

	/**
	 * @return User
	 */
	public function getUser();

	/**
	 * @param User $user
	 * @return void
	 */
	public function setUser($user);

	/**
	 * @param string $data
	 * @return self
	 */
	public function send($data);

} 