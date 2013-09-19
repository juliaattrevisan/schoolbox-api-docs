<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$instanceId = intval($_GET['instanceId']);
$homepageId = intval($_GET['homepageId']);
$start = intval($_GET['start']);
$offset = intval($_GET['offset']);

$instanceArray = \Component_InstanceMapper::getInstance()->findByInstanceId($instanceId);
$instance = $instanceArray[$instanceId];

/* Sew thread if instance exists
 */
if ($instance) {
	$thread = new Threads($instance, $homepageId, $start, $offset);
	$output = $thread->generate()->render();

	echo $output;
}


