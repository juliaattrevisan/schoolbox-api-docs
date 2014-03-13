<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// disallow SitePage context
if(isset($this)) {
	SitePage::localRedir('/settings/messages');
}

require_once __DIR__ . '/../../include/Bootstrap.php';

// magic initialisation of session
$p = new SitePage();

if(!(is_array($_POST) && count($_POST))) {
	error(2);
}

/** @var \Schoolbox\User $user */
$user = $_SESSION['user'];

$userId = $user->getId();
$roleId = $user->getRole()->getId();

$selectstmt = Database::getInstance()->prepare("
	SELECT 	:userId `relation_id`, 'user' `relation_type`, src.`routing_key`,
			perm.`default_level` `incoming_level`, :outgoingLevel `outgoing_level`, perm.min_level, perm.max_level
	FROM `msg_source` src
	LEFT JOIN msg_namespace ns ON src.namespace = ns.id
	LEFT JOIN msg_route_perm perm ON perm.msg_source_id = src.id AND perm.role_id = :roleId
	WHERE ns.slug = :slug
	AND `key` = :key
");

$insertstmt = Database::getInstance()->prepare("
	INSERT INTO `msg_reroute` (
		`relation_id`, `relation_type`, `routing_key`, `incoming_level`, `outgoing_level`
	) VALUES (
		:relation_id, :relation_type, :routing_key, :incoming_level, :outgoing_level
	) ON DUPLICATE KEY UPDATE
		`outgoing_level` = :outgoing_level
");



foreach($_POST as $ns => $options) {
	if(!is_array($options)) {
		continue;
	}

	foreach($options as $key => $level) {
		// use $userId, $ns, $key, and $level
		$selectstmt->execute(array(
			'userId'        => $userId,
			'roleId'        => $roleId,
			'slug'          => $ns,
			'key'           => $key,
			'outgoingLevel' => $level
		));

		$permission = $selectstmt->fetch(PDO::FETCH_ASSOC);

		if(is_array($permission) && count($permission)) {
			if($permission['outgoing_level'] > $permission['max_level']) {
				$permission['outgoing_level'] = $permission['max_level'];
			}

			if($permission['outgoing_level'] < $permission['min_level']) {
				$permission['outgoing_level'] = $permission['min_level'];
			}

			$insertstmt->execute(array(
				'relation_id'    => $permission['relation_id'],
				'relation_type'  => $permission['relation_type'],
				'routing_key'    => $permission['routing_key'],
				'incoming_level' => $permission['incoming_level'],
				'outgoing_level' => $permission['outgoing_level']
			));
		}
	}
}

$p->localRedir('/settings/messages');
