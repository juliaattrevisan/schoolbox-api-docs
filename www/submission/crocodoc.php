<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* This is intended to load in an iframe
 */
$crocodoc = new \Crocodoc\Facade($_GET['submissionId'], $_SESSION['user']);
$crocodoc->start();

exit();