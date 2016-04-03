<?php
use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

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

if (isset($_POST['follow'])) {
    $follow = $_POST['follow'];
    unset($_POST['follow']);
} else {
    $follow = array();
}

$involvements = $user->getAccessList()->getColumnValues('serviceIdentifier');

$follow = array_keys(array_filter($follow));

asort($follow);
asort($involvements);

$unfollow = array_values(array_diff($involvements, $follow));

/**
 * Wrap an int into a routing-key string
 *
 * @param int $v
 * @return string
 */
function castToRoutingKey($v) {
    return 'service.' . UserAccess::SERVICE_HOMEPAGE . ".$v";
}

// delete any overrides for homepages the user is RE-following
RerouteOverrideMapper::getInstance()->deleteByColumnList(array(
    'user_id'        => $userId,
    'msg_route_key'  => array_map('castToRoutingKey', $follow),
    'outgoing_level' => 0
));

// insert new entries for homepages the user is UN-following
$overrides = new Collection();

foreach ($unfollow as $id) {
    $override = new RerouteOverride();

    $override->setUser($user);
    $override->setRouteKey(castToRoutingKey($id));
    $override->setOutgoingLevel(0);

    $overrides->add($override);
}

RerouteOverrideMapper::getInstance()->save($overrides);


$selectstmt = Database::getInstance()->prepare("
	SELECT 	:userId `user_id`, src.id `msg_source_id`,
			:outgoingLevel `outgoing_level`, perm.min_level, perm.max_level
	FROM `msg_source` src
	LEFT JOIN msg_namespace ns ON src.namespace = ns.id
	RIGHT JOIN msg_route_perm perm ON perm.msg_source_id = src.id AND perm.role_id = :roleId
	WHERE ns.slug = :slug
	AND `key` = :key
");

$insertstmt = Database::getInstance()->prepare("
	INSERT INTO `msg_reroute` (
		`user_id`, `msg_source_id`, `outgoing_level`
	) VALUES (
		:user_id, :msg_source_id, :outgoing_level
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

			unset($permission['min_level']);
			unset($permission['max_level']);

			$insertstmt->execute($permission);
		}
	}
}

$p->localRedir('/settings/messages');
