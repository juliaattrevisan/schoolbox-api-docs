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
$limit = intval($_GET['limit']);
$dateThreshold = $_GET['dateThreshold'];

$instance = \Component_InstanceMapper::getInstance()->findById($instanceId);

/* Sew thread if instance exists
 */
if ($instance) {

	/* Enforce a $dateThreshold string
	 */
	if (!strlen($dateThreshold)) {
		$dateThreshold = '0000-00-00 00:00:00';
	}

	$thread = new Threads($instance, $limit, $dateThreshold);
	$output = $thread->generate()->render();

	echo $output;
}


