<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif;

/**
 * Provides a centralised, non-magic way of specifying the various targets of notification.
 *
 * Class TopicFactory
 * @package Schoolbox\Notif
 */
class TopicFactory {

	/**
	 * @param $userId
	 * @return string
	 */
	public function targetUser($userId) {
		return "notify.user.$userId";
	}

	/**
	 * @param $categoryId
	 * @return string
	 */
	public function targetCategory($categoryId) {
		return "notify.category.$categoryId";
	}

	/**
	 * @param $categoryId
	 * @return string
	 */
	public function targetCategoryAdmin($categoryId) {
		return "notify.category.$categoryId.admin";
	}

	/**
	 * @param $campusId
	 * @return string
	 */
	public function targetCampus($campusId) {
		return "notify.campus.$campusId";
	}

	/**
	 * @param $roleId
	 * @return string
	 */
	public function targetRole($roleId) {
		return "notify.role.$roleId";
	}

	/**
	 * @param $roleType
	 * @return string
	 */
	public function targetRoleType($roleType) {
		return "notify.roletype.$roleType";
	}

	/**
	 * @param $topic
	 * @return string
	 */
	public function targetSuperusers($topic) {
		return "notify.superuser.$topic";
	}

	/**
	 * @param $houseId
	 * @return string
	 */
	public function targetSchoolHouse($houseId) {
		return "notify.house.$houseId";
	}

	/**
	 * @param $resId
	 * @return string
	 */
	public function targetResidentialHouse($resId) {
		return "notify.reshouse.$resId";
	}


} 