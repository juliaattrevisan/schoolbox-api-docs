<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

$user_mapper = new \Schoolbox\UserMapper();
$user = $user_mapper->findById($_GET['userId']);
try {
    Calendar_Layout_Full_Javascript::permissionCheck($_GET['userId'], $_SESSION['user'], new Group());
}
catch (Exception $e) {
    error($e->getCode());
}

$calendar = new Calendar_Layout_Full_Ajax_JSON($this, $user, $this->arg[0], $_SESSION['user']);
$calendar->render();