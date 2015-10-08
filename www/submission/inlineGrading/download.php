<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$crocodoc = new \Crocodoc\Facade($this->arg[0], $_SESSION['user']);
$crocodoc->download();
exit();
