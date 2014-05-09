<?php
/**
 * Clears password
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
$_SESSION['password'] = '';

new \Alaress_Probe($_SESSION);