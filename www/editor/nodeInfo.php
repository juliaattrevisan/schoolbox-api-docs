<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once('../../include/Bootstrap.php');

// kickstart a session
new SitePage();

// disallow SitePage context, this is an ajax script
if (isset($this)) {
    SitePage::localRedir('/editor/nodeInfo.php');
    exit;
}

$factory = new \Schoolbox\Editor\FileFactory($_GET['hash']);

header('Content-Type: application/json');
echo $factory->produce();
exit();
