<?php
/**
 * Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Etherpad;


class Authenticator {

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Determine if you can create a pad for $homepageId
	 *
	 * @param $homepageId
	 * @return bool
	 */
	public function canCreate($homepageId) {
		$groupHandler = new \Group();

		if ($groupHandler->getPermission($homepageId) > READ_ACCESS) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if a user is part of an authoring group
	 *
	 * @param $groupId
	 * @param $userId
	 * @return bool
	 */
	public function canEdit($groupId, $userId) {
		$etherpadGroupMapper = new GroupMapper();

		$exists = $etherpadGroupMapper->findByColumnList(array(
			'group_id' => $groupId,
			'user_id' => $userId
		));

		if ($exists->count()) {
			return true;
		}

		return false;
	}
} 