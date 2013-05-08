<?php
/**
 * Eportfolio_PermissionMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Eportfolio;
use Eportfolio;
use Schoolbox;

class PermissionMapper extends Schoolbox\Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolio_permission');
		$this->hasModel('\\Eportfolio\\Permission');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'page_id',
			'property'   => 'page_id',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'type',
			'property'   => 'type',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'value',
			'property'   => 'value',
			'type'       => self::TYPE_INTEGER,
		));

		parent::mapping();
	}

	/**
	 * Facade method
	 *
	 * Create/update all permissions for a particular page
	 *
	 * @param $pageId
	 * @param $parent
	 * @param $group
	 * @param $individual
	 */
	public function updatePermissions($pageId, $parent, $group, $individual) {
		$this->removeAllPermissions($pageId);

		$this->addParentPermissions($pageId, $parent);
		$this->addGroupPermissions($pageId, $group);
		$this->addIndividualPermissions($pageId, $individual);
	}

	/**
	 * Remove all permissions for a particular page
	 *
	 * @param $pageId
	 */
	private function removeAllPermissions($pageId) {
		$statement = $this->database->prepare("
            DELETE FROM  `{$this->table}`
            WHERE page_id = :identifierValue
        ");

		$statement->bindParam(':identifierValue', $pageId);
		$statement->execute();
	}

	/**
	 * Add parent permissions to a particular page
	 *
	 * @param $pageId
	 * @param $parent
	 */
	private function addParentPermissions($pageId, $parent) {
		if (is_numeric($parent)) {
			$permission = new Eportfolio_Permission();
			$permission->setPageId($pageId);
			$permission->setType('parent');
			$permission->setValue($parent);

			$this->save($permission);
		}
	}

	/**
	 * Add group permissions to a particular page
	 *
	 * @param $pageId
	 * @param $allGroups
	 */
	private function addGroupPermissions($pageId, $allGroups) {
		if (is_array($allGroups) && count($allGroups)) {
			foreach($allGroups as $currentGroup) {
				if (is_numeric($currentGroup)) {
					$permission = new Eportfolio_Permission();
					$permission->setPageId($pageId);
					$permission->setType('group');
					$permission->setValue($currentGroup);

					$this->save($permission);
				}
			}
		}
	}

	/**
	 * Add individual permissions to a particular page
	 *
	 * @param $pageId
	 * @param $allIndividuals
	 */
	private function addIndividualPermissions($pageId, $allIndividuals) {
		if (is_array($allIndividuals) && count($allIndividuals)) {
			foreach($allIndividuals as $currentIndividual) {
				if (is_numeric($currentIndividual)) {
					$permission = new Eportfolio_Permission();
					$permission->setPageId($pageId);
					$permission->setType('student');
					$permission->setValue($currentIndividual);

					$this->save($permission);
				}
			}
		}
	}

	/**
	 * Find the Parent permission settings for a particular page
	 *
	 * @param $pageId
	 * @return Bool|Collection
	 */
	public function findParentSetting($pageId) {
		return $this->findByColumnList(array(
			'page_id' => $pageId,
			'type' => 'parent',
		));
	}

	/**
	 * TODO: PDO Array support for using WHERE IN
	 *
	 * http://stackoverflow.com/questions/14767530/php-using-pdo-with-in-clause-array
	 * http://stackoverflow.com/questions/920353/php-pdo-can-i-bind-an-array-to-an-in-condition
	 *
	 * @param $pageId
	 * @param $type
	 * @param $permittedValues
	 * @return Collection
	 */
	public function findValues($pageId, $type, $permittedValues) {
		$permittedValuesSql = implode(',', $permittedValues);

		$statement = $this->database->prepare("
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            WHERE `page_id` = :pageId
			AND `type` = :type
			AND `value` IN ({$permittedValuesSql})

        ");

		$statement->bindParam(':pageId', $pageId);
		$statement->bindParam(':type', $type);
		$statement->execute();

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}
}