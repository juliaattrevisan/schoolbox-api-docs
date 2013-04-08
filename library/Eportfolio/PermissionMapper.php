<?php
/**
 * Eportfolio_PermissionMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_PermissionMapper extends Mapper {

	/**
	 * Database table
	 *
	 * @var string
	 */
	protected $table = 'eportfolio_permission';

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => Mapper::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'page_id',
			'property'   => 'page_id',
			'type'       => Mapper::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'type',
			'property'   => 'type',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'value',
			'property'   => 'value',
			'type'       => Mapper::TYPE_INTEGER,
		));
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
}