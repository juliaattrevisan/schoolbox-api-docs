<?php
/**
 * Mapper between user objects and database rows.
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox;

class UserMapper extends Mapper {

	protected function mapping () {

		$this->hasTable('user');
		$this->hasModel('\Schoolbox\User');

		$this->hasMap(array(
				'column'     => 'id',
				'property'   => 'id',
				'type'       => Mapper::TYPE_INTEGER,
				'identifier' => true
			));

		$this->hasMap(array(
				'column'     => 'username',
				'property'   => 'username',
				'type'       => Mapper::TYPE_STRING,
			));

		$this->hasMap(array(
				'column'     => 'password',
				'property'   => 'password',
				'type'       => Mapper::TYPE_STRING,
			));


		$this->hasMap(array(
				'column'     => 'title',
				'property'   => 'title',
				'type'       => Mapper::TYPE_STRING,
			));

		$this->hasMap(array(
				'column'     => 'firstname',
				'property'   => 'firstname',
				'type'       => Mapper::TYPE_STRING,
			));

		$this->hasMap(array(
				'column'     => 'lastname',
				'property'   => 'lastname',
				'type'       => Mapper::TYPE_STRING,
			));


		$this->hasMap(array(
				'column'     => 'synergy_id',
				'property'   => 'externalId',
				'type'       => Mapper::TYPE_INTEGER,
			));

		$this->hasMap(array(
				'column'     => 'year',
				'property'   => 'year',
				'type'       => Mapper::TYPE_INTEGER,
			));

		$this->hasMap(array(
				'column'     => 'house',
				'property'   => 'schoolHouse',
				'type'       => Mapper::TYPE_INTEGER,
			));

		$this->hasMap(array(
				'column' => 'residential_house_id',
				'property' => 'residentialHouse',
				'type' => Mapper::TYPE_INTEGER,
			));


		$this->hasMap(array(
				'column'     => 'local_email',
				'property'   => 'localEmail',
				'type'       => Mapper::TYPE_BOOLEAN,
			));

		$this->hasMap(array(
				'column'     => 'alt_email',
				'property'   => 'altEmail',
				'type'       => Mapper::TYPE_STRING,
			));

		$this->hasMap(array(
				'column'     => 'mail_handler',
				'property'   => 'useInternalMail',
				'type'       => Mapper::TYPE_INTEGER,
			));

		$this->hasMap(array(
				'column'     => 'hash',
				'property'   => 'hash',
				'type'       => Mapper::TYPE_STRING,
			));

		$this->hasMap(array(
				'column'     => 'key',
				'property'   => 'key',
				'type'       => Mapper::TYPE_STRING,
			));

		$this->hasMap(array(
				'column'     => 'eportfolio_enabled',
				'property'   => 'eportfolioEnable',
				'type'       => Mapper::TYPE_STRING,
			));

		parent::mapping();
	}

	public function findById($id) {

		$user = false;
		$roleMapper = new RoleMapper;
		$campusMapper = new CampusMapper;

		$statement = $this->database->prepare( "
			SELECT {$this->getColumnString('u')}, IF(su.id, 1, 0) as `u.superuser`,
					{$roleMapper->getColumnString('g')},
					{$campusMapper->getColumnString('c')}, uc.defaultCampus as `uc.defaultCampus`
					FROM `user` u
			LEFT JOIN `groups` g ON group_id = g.`id`
			LEFT JOIN userCampus uc ON uc.`uid`=u.id
			LEFT JOIN superusers su ON su.id = u.id
			INNER JOIN luCampus c ON c.id = uc.`campus`
			WHERE u.id = ?
			" );

		$statement->execute(array($id));

		foreach( $statement as $row ) {
			if(!is_object($user)){
				$user = $this->hydrate($row, 'u');
				$user->setSuperUser($row['u.superuser'] == 1);

				$user->setRole($roleMapper->hydrate($row, 'g'));
			}

			$campus = $campusMapper->hydrate($row, 'c');

			$user->getCampusCollection()->add($campus);

			if($row['uc.defaultCampus'] == 1) {
				$user->setDefaultCampus($campus);
			}
		}
		return $user;
	}


	/**
	 * Fetches the user, and all of the user's subjects.
	 *
	 * @param $id
	 *
	 * @return \Schoolbox\User
	 */
	public function findWithSubjects($id) {
		$statement = $this->database->prepare(
			"SELECT {$this->getColumnString('u')} FROM {$this->table} AS u WHERE u.id=?"
		);
		$statement->execute(array($id));

		if ($row = $statement->fetch()) {
			$user = $this->hydrate($row, 'u');
			//
			// Set subject collection here.
			$subject_mapper = new External\SubjectMapper();
			$user->setSubjectCollection($subject_mapper->findAllForStudent($user));
			return $user;
		}
		return false;
	}

	/**
	 * Find all the users which match $name and are in group_id array $permittedValues
	 *
	 * @param $name
	 * @param $permittedValues
	 * @return bool|\Model
	 */
	public function findByNameAndGroup($name, $permittedValues) {
		$collection = new Schoolbox\Collection();
		$roleMapper = new RoleMapper();

		$permittedValuesSql = implode(',', $permittedValues);

		$query = "
			SELECT
				{$this->getColumnString('u')},
				{$roleMapper->getColumnString('g')}
			FROM `{$this->table}` u
			LEFT JOIN `groups` g ON group_id = g.`id`
			WHERE (firstname LIKE ? OR lastname LIKE ?)
			AND group_id IN ({$permittedValuesSql})
        ";

		$statement = $this->database->prepare($query);

		$statement->execute(array("%$name%", "%$name%"));

		foreach ($statement as $row) {
			$user = $this->hydrate($row, 'u');
			$user->setRole($roleMapper->hydrate($row, 'g'));

			$collection->add($user);
		}

		return $collection;
	}

	/**
	 * Fetches a single user ID, for use by GizaUser_DataMapper.
	 *
	 * This is to be @deprecated when the whole User object is stored in the
	 * session, but has been added for now because it's injection safe (unlike
	 * the Giza mappers)
	 *
	 * @param string $username
	 * @return int
	 */
	public function getIdForUsername($username) {
		$query = "SELECT `id` FROM `{$this->getTable()}` WHERE `username` = :username";
		$statement = $this->database->prepare($query);
		$statement->bindValue('username', $username, \PDO::PARAM_STR);
		$statement->execute();
		return $statement->fetchColumn();
	}
}
