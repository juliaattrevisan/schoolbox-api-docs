<?php
/**
 * sync
 *
 * Import data from random external CSV source and put
 * it into database.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../../library/Bootstrap.php');

$sync = new Synchronise_External_Donvale_Facade();
$sync->process();
