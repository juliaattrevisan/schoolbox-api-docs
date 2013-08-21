<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Schoolbox\Resource;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\UserAccess;
use \External_UserInterface;


class CategoryMapper extends Mapper {

	/**
	 * Interface with external user database.
	 *
	 * @var External_UserInterface
	 */
	protected $external_user;

	protected function mapping() {
		$this->hasModel('\\Schoolbox\\Resource\\Category');
		$this->hasTable('category');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'parent',
			'property'   => 'parentId',
			'type'       => self::TYPE_INTEGER,
		));

		//
		// Joins:
		$this->hasJoin('codes', array(
			'multiple'  => true,
			'mapper'    => '\\Schoolbox\\Resource\\CategoryCodeMapper',
			'this_key'  => 'id',
			'other_key' => 'categoryID',
		));

		$this->hasJoin('homepages', array(
            'multiple'  => false,
            'mapper'    => '\\Schoolbox\\Resource\\HomepageConfigMapper',
            'this_key'  => 'id',
            'other_key' => 'cid',
        ));

		$this->hasJoin('files', array(
			'multiple'  => true,
			'mapper'    => '\\Schoolbox\\Resource\\FileMapper',
			'this_key'  => 'id',
			'other_key' => 'category',
		));

        $this->hasJoin('groups', array(
                'multiple'  => true,
                'mapper'    => '\\Schoolbox\\Resource\\GroupMapper',
                'this_key'  => 'id',
                'other_key' => 'cid',
            ));

		//
		// Parent object: has reference to itself as a mapper.
		$this->hasJoin('parent', array(
			'multiple'  => true,
			'mapper'    => $this,
			'this_key'  => 'category',
			'other_key' => 'id',
		));

	}


	/**
	 * Get a list of categorys which belong to a particular parent
	 *
	 * @param $id
	 * @return Category
	 * @throws \Schoolbox\Mapper\Exception
	 */
	public function findByParent($id) {
		$code_mapper = $this->getJoinMapper('codes');
		$homepageConfig_mapper = $this->getJoinMapper('homepages');
		$group_mapper = $this->getJoinMapper('groups');
		$query = "
			SELECT
				{$this->getColumnString('c')},
				{$code_mapper->getColumnString('cc')},
				{$homepageConfig_mapper->getColumnString('hp')},
				{$group_mapper->getColumnString('ug')}
			FROM {$this->getTable()} c
			LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
			LEFT JOIN {$this->getJoinSql('homepages', 'c', 'hp')}
			LEFT JOIN {$this->getJoinSql('groups', 'c','ug')}
			WHERE c.parent=?
			ORDER BY c.name
		";

		$statement = $this->database->prepare($query);
		$statement->execute(array($id));

		$collection = new \Schoolbox\Collection();
		$category = null;

		foreach ($statement as $row) {
			$category = $this->hydrate($row, 'c');
			$code = $code_mapper->hydrate($row, 'cc');

			if ($code->getCategory()->getId()) {
				$category->addCode($code);
			}

			$homepageConfig = $homepageConfig_mapper->hydrate($row, 'hp');
			$category->setHomepage($homepageConfig);

			$group = $group_mapper->hydrate($row, 'ug');
			$category->setGroup($group);

			$collection->add($category);
		}

		return $collection;
	}

	public function findById($id) {
		$code_mapper = $this->getJoinMapper('codes');
        $homepageConfig_mapper = $this->getJoinMapper('homepages');
        $group_mapper = $this->getJoinMapper('groups');
		$query = "
			SELECT
				{$this->getColumnString('c')},
				{$code_mapper->getColumnString('cc')},
				{$homepageConfig_mapper->getColumnString('hp')},
				{$group_mapper->getColumnString('ug')}
			FROM {$this->getTable()} c
			LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
			LEFT JOIN {$this->getJoinSql('homepages', 'c', 'hp')}
			LEFT JOIN {$this->getJoinSql('groups', 'c','ug')}
			WHERE c.id=?
		";

		$statement = $this->database->prepare($query);
		$statement->execute(array($id));

		$category = null;
		foreach ($statement as $row) {
			if (!$category instanceof $this->model) {
				$category = $this->hydrate($row, 'c');
			}
			$code = $code_mapper->hydrate($row, 'cc');
			if ($code->getCategory()->getId()) {
				$category->addCode($code);
			}
			$homepageConfig = $homepageConfig_mapper->hydrate($row, 'hp');
			$category->setHomepage($homepageConfig);

			$group = $group_mapper->hydrate($row, 'ug');
			$category->setGroup($group);
		}
		if (!($category instanceof Category)) {
			throw new \Schoolbox\Mapper\Exception('Instance of object not found');
		}
		return $category;
	}

	/**
	 * Port of \Group->involvedInCategoryList() method.
	 *
	 * @param User $user         The user to search for.
	 * @param bool $get_subjects If false, subjects aren't retrieved.
	 * @param int  $access_level Defaults to UserAccess::ACCESS_READ, but may be
	 *                           any access level.
	 *
	 * @return \Schoolbox\Collection
	 */
	public function findForUser(User $user, $get_subjects = true, $access_level = UserAccess::ACCESS_READ) {
		$query = "
			SELECT DISTINCT {$this->getColumnString('c')}
			FROM {$this->getTable()} c
			LEFT JOIN userAccess a ON c.id=a.sid AND a.service=:service
			LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
			WHERE a.uid=:user
		";
		$params = array(
			'user'    => $user->getId(),
			'service' => $access_level,
		);

		//
		// If getting subjects - get the external user mapper and query that.
		if ($get_subjects) {
			$external_user = $this->getExternalUser();
			if ($external_user instanceof External_UserInterface) {
				$subject_list = $external_user->getSubjectList($user->getExternalId(), $user->getId());
				if (is_array($subject_list) && !empty($subject_list)) {
					$category_codes = array_map(function($row) { return $row['code']; }, $subject_list);
					if (!empty($category_codes)) {
						$query .= ' OR cc.code IN '.$this->getArrayPlaceholders($category_codes, 'code');
						$params = array_merge($params, $this->getArrayParameters($category_codes, 'code'));
						//
						// Allow search of codes with a campus prefix.
						$category_like_codes = array_map(
							function($code) { return '%/'.$code; },
							$category_codes
						);
						foreach ($category_like_codes as $i => $like_code) {
							$query .= ' OR cc.code LIKE :like_code_'.$i;
							$params['like_code_'.$i] = $like_code;
						}
					}
				}
			}
		}

		$statement = $this->database->prepare($query);
		$statement->execute($params);
		return $this->hydrateList($statement, 'c');
	}

	/**
	 * @param  \External_UserInterface $external_user
	 *
	 * @todo Have this be set up automatically if at all possible
	 *
	 * @return $this
	 */
	public function setExternalUser($external_user) {
		$this->external_user = $external_user;
		return $this;
	}

	/**
	 * @return \External_UserInterface
	 */
	public function getExternalUser() {
		return $this->external_user;
	}

}