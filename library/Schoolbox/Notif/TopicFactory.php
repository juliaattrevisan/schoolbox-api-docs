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
		return $this->targetCategory($categoryId) .".admin";
	}

	/**
	 * @param $categoryId
	 * @return string
	 */
	public function targetCategoryStaff($categoryId) {
		return $this->targetCategory($categoryId) .".staff";
	}

	/**
	 * @param $categoryId
	 * @return string
	 */
	public function targetCategoryStudents($categoryId) {
		return $this->targetCategory($categoryId) .".student";
	}

	/**
	 * @param $campusId
	 * @return string
	 */
	public function targetCampus($campusId) {
		return "notify.campus.$campusId";
	}

	/**
	 * @param $campusId
	 * @return string
	 */
	public function targetCampusStaff($campusId) {
		return $this->targetCampus($campusId) . ".staff";
	}

	/**
	 * @param $campusId
	 * @return string
	 */
	public function targetCampusStudents($campusId) {
		return $this->targetCampus($campusId) . ".student";
	}

	/**
	 * @param $campusId
	 * @return string
	 */
	public function targetCampusParents($campusId) {
		return $this->targetCampus($campusId) . ".parent";
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
	 * @param $houseId
	 * @return string
	 */
	public function targetSchoolHouseStaff($houseId) {
		return $this->targetSchoolHouse($houseId) . ".staff";
	}

	/**
	 * @param $houseId
	 * @return string
	 */
	public function targetSchoolHouseStudents($houseId) {
		return $this->targetSchoolHouse($houseId) . ".student";
	}

	/**
	 * @param $resId
	 * @return string
	 */
	public function targetResidentialHouse($resId) {
		return "notify.reshouse.$resId";
	}

	/**
	 * @param $resId
	 * @return string
	 */
	public function targetResidentialHouseStaff($resId) {
		return $this->targetResidentialHouse($resId) . ".staff";
	}

	/**
	 * @param $resId
	 * @return string
	 */
	public function targetResidentialHouseStudents($resId) {
		return $this->targetResidentialHouse($resId) . ".student";
	}

	/**
	 * @param $userId
	 * @return string
	 */
	public function targetParentsOfStudent($userId) {
		return "notify.parent.$userId";
	}

} 