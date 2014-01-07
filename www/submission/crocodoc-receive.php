<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage(false);

new \Alaress_Probe($_POST);

new \Alaress_Probe($_FILES);

echo 'crocodoc receive';