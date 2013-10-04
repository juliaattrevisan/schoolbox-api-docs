<?php
/**
 * Render the javascript-based Fullcalendar implementation
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
$user_mapper = new \Schoolbox\UserMapper();
$user = $user_mapper->findById($_SESSION['id']);

$calendar = new Calendar_Layout_Full_Javascript($this, $user, $this->arg[0]);
$calendar->render();
