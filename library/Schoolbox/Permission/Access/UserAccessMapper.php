<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Permission\Access;


use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Category;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class UserAccessMapper extends Mapper {

	/**
	 * @return void
	 */
	protected function mapping() {
		$this->hasTable('userAccess');
		$this->hasModel('\\Schoolbox\\Permission\\Access\\UserAccess');

		$this->hasMap(array(
			'column'     => 'uid',
			'property'   => 'user',
			'type'       => self::TYPE_MODEL,
			'identifier' => true,
			'join'       => 'user'
		));

		$this->hasMap(array(
			'column'     => 'service',
			'property'   => 'service',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'sid',
			'property'   => 'serviceIdentifier',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'type',
			'property'   => 'accessLevel',
			'type'       => self::TYPE_INTEGER
		));

		$this->hasJoin('user', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'uid',
			'other_key' => 'id'
		));

	}


	/**
	 * @param Category $category
	 * @param bool $includeSynergy
	 * @return Collection|User[]
	 */
	public function findStaffInCategory($category, $includeSynergy = true) {
		return $this->findUsersInCategory($category, Role::STAFF, UserAccess::ACCESS_READ, $includeSynergy);
	}


	/**
	 * @param Category $category
	 * @param bool $includeSynergy
	 * @return Collection|User[]
	 */
	public function findStudentsInCategory($category, $includeSynergy = true) {
		return $this->findUsersInCategory($category, Role::STUDENT, UserAccess::ACCESS_READ, $includeSynergy);
	}

    /**
     * Returns a list of users who are members of a group
     *
     * @param Category $category
     * @return Collection|User[]
     */
    public function findAdminsInCategory($category) {
        //TODO role type should be optional
        return $this->findUsersInCategory($category, Role::STAFF, $accessType = UserAccess::ACCESS_FULL, false);
    }


    /**
	 * @param Category $category
	 * @param string $roleType
     * @param int $accessType
	 * @param bool $includeSynergy
	 * @return Collection|User[]
	 */
	public function findUsersInCategory($category, $roleType, $accessType = UserAccess::ACCESS_READ, $includeSynergy = true) {
		// union 3 sources: userAccess, and if synergy, external class and external user

		// acquire users from internal access definition
		$stmt = $this->database->prepare("
			SELECT
				{$this->getJoinColumnString('user', 'u')}
			FROM
				`{$this->table}` ua
				LEFT JOIN {$this->getJoinSql('user', 'ua', 'u')}
				LEFT JOIN groups g on u.group_id = g.id
			WHERE ua.service = :service
			AND ua.sid = :serviceId
			AND ua.type >= :minAccess
			AND g.group_type = :roleType
		");

		$stmt->execute(array(
			'service'   => UserAccess::SERVICE_HOMEPAGE,
			'serviceId' => $category->getId(),
			'minAccess' => $accessType,
			'roleType'  => $roleType
		));
		$userCollection = $this->createCollection();

		while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
			// add the user represented by this row to the collection
			$userCollection->add(
				$this->hydrateJoin($r, 'user', 'u')
			);
		}

		if(!$includeSynergy) {
			// no synergy? ok, we're done here
			return $userCollection;
		}

        // TODO get rid of these
        $_DEPRECATED_EXTERNAL_CLASS_HANDLER = new \External_Class();

		// including synergy: intersect the existing collection with synergy-based collections

		// start with a list of students in this category
		switch($roleType) {
			case Role::STUDENT:
				$classListArray = $_DEPRECATED_EXTERNAL_CLASS_HANDLER->getStudents($category->getCodeString()) ?: array();
				break;
			case Role::STAFF:
				$classListArray = $_DEPRECATED_EXTERNAL_CLASS_HANDLER->getTeacherName($category->getCodeString()) ?: array();
				break;
			default:
				$classListArray = array();
		}

		$synergyIdArray = array();

		// index by synid
		foreach($classListArray as $array) {
			$synergyIdArray[$array['synergy_id']] = $array['synergy_id'];
		}

		$extUserCollection = UserMapper::getInstance()->findByColumnList(array(
			'synergy_id' => array_values($synergyIdArray),
			'group_id'   => RoleMapper::getInstance()->findByColumn('group_type', $roleType)->getColumnValues('id')
		));

		// merge the two collections
		$extUserCollection->mergeUnique($userCollection, 'id');
		return $extUserCollection;
	}



} 