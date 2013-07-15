<?php
/**
 * Mapper for subjects stored within external database.
 *
 * While subjects will be stored in an external database, they will have a
 * corresponding Category object within Schoolbox.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\External;
use Schoolbox;

class SubjectMapper extends Schoolbox\External\Mapper {

	/**
	 * Defines mapping for this mapper.
	 *
	 * @return voids
	 */
	protected function mapping() {
		//
		// has an external table, not a Schoolbox one.
		// Field mappings may not be completely appropriate.
		$this->hasModel('\Schoolbox\External\Subject');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'code',
			'property'   => 'code',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));
		parent::mapping();
	}


	/**
	 * Retrieves subject by Schoolbox ID.
	 *
	 * @param int $id
	 * @return mixed
	 */
	public function findById($id) {
		$statement = $this->database->prepare('
			SELECT c.id, c.name, GROUP_CONCAT(co.code) AS code
			FROM category c
			LEFT JOIN categoryCode co ON co.categoryID = c.id
			WHERE co.id=?
		');
		$statement->execute(array($id));
		return $statement->fetch();
}


	/**
	 * Gets all subjects for the given student.
	 *
	 * @param $user
	 *
	 * @return SubjectCollection
	 */
	public function findAllForStudent(Schoolbox\User $user) {
		$subjects = $this->external_user->getSubjectList($user->getExternalId(), $user->getId());

		// if no subjects, just return an empty collection
		if($subjects === false) {
			return new SubjectCollection();
		}

		$category_statement = $this->database->prepare('
			SELECT c.id, GROUP_CONCAT(co.code) AS code
			FROM category c
			LEFT JOIN categoryCode co ON co.categoryID = c.id
			WHERE co.code=?
		');
		$add_id_map = function($subject) use ($category_statement) {
			$category_statement->execute(array($subject['code']));
			if ($row = $category_statement->fetch()) {
				$subject['id']   = $row['id'];
//				$subject['code'] = $row['code'];
			}
			return $subject;
		};
		$id_filter = function($subject) {
			return isset($subject['id']);
		};
		return new SubjectCollection(
			array_filter(array_map($add_id_map, $subjects), $id_filter)
		);
	}
}
