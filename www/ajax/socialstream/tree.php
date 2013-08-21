<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../../include/Bootstrap.php';

$page = new SitePage();

$browser = new \SocialStream\Browser\Node($_REQUEST['relationID']);
$browser->render();
