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

/* For Samuel
 */
$_SERVER['HTTP_HOST'] = "";

$migrator = new \Eportfolio\Migrate();
$migrator->migrateAll();
