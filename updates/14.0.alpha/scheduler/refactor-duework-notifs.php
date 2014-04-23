<?php
/**
 * This is really only a PHP script so as to ensure it executes *after* the new table is created
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(__DIR__ . '/../../../include/Bootstrap.php');

$select = Database::getInstance()->prepare("
	SELECT box_id, src.key, src.role_type
	FROM submission_notification sn
	LEFT JOIN msg_source src ON src.key = sn.type AND src.namespace = 1
	WHERE enabled = 0
	ORDER BY src.id, sn.box_id
");

$update = Database::getInstance()->prepare("
	INSERT INTO msg_reroute_override (user_id, msg_route_key, outgoing_level)
	VALUES (0, :route, 0)
	ON DUPLICATE KEY UPDATE outgoing_level = 0
");

$select->execute();

while($r = $select->fetch(PDO::FETCH_ASSOC)) {
	$update->execute(array(
		'route' => 'duework.'.$r['box_id'].'.'.$r['key'],
	));
}
