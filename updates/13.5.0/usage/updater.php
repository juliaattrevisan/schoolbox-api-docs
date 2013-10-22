<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../../../include/Bootstrap.php');

/* Update submissions table with filesizes
 * Update returns table with mime types
 */
$updater = new \Schoolbox\Usage\Updater();
$updater->updateSubmissions();
$updater->updateReturns();