<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

new \Alaress_Probe($_SESSION);

$crocodoc = new \Crocodoc\Loader('cbad641e-cfa4-4a57-83e7-168956f208a0', $_SESSION['user']);
$crocodoc->render();


