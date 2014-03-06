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

//echo "<pre>";
//print_r($_POST);
//echo "\n</pre>";

/** @var \Schoolbox\User $user */
$user = $_SESSION['user'];

$userId = $user->getId();
$roleId = $user->getRole()->getId();

$selectstmt = Database::getInstance()->prepare("
	SELECT 	:userId `relation_id`, 'user' `relation_type`, ms.`routing_key`,
			mp.`default_level` `incoming_level`, :outgoingLevel `outgoing_level`, mp.min_level, mp.max_level
	FROM `msg_source` ms
	LEFT JOIN msg_namespace mn ON ms.namespace = mn.id
	LEFT JOIN msg_route_perm mp ON mp.msg_source_id = ms.id AND mp.role_id = :roleId
	WHERE mn.slug = :slug
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

		$foo = $selectstmt->fetch(PDO::FETCH_ASSOC);

		if($foo['outgoing_level'] > $foo['max_level']) {
			$foo['outgoing_level'] = $foo['incoming_level'];
		}
		unset($foo['max_level']);

		if($foo['outgoing_level'] < $foo['min_level']) {
			$foo['outgoing_level'] = $foo['incoming_level'];
		}
		unset($foo['min_level']);

		// the result of the previous is the args of the next
		if(count($foo) == 5) {
			$insertstmt->execute($foo);
		}
	}

$p->backToTheFuture(1);

}