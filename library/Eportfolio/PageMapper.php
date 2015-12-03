<?php
/**
 * Eportfolio_PageMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Eportfolio;
use Eportfolio;
use Schoolbox;

class PageMapper extends Schoolbox\Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolio_page');
		$this->hasModel('\\Eportfolio\\Page');

		$this->hasMap(array(
			'column'     => 'page_id',
			'property'   => 'pageId',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'page_title',
			'property'   => 'pageTitle',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'uid',
			'property'   => 'uid',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'template',
			'property'   => 'template',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'left_column_width',
			'property'   => 'leftColumnWidth',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_head_font',
			'property'   => 'leftHeadFont',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_head_bg',
			'property'   => 'leftHeadBg',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_font',
			'property'   => 'leftFont',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_bg_primary',
			'property'   => 'leftBgPrimary',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_column_width',
			'property'   => 'rightColumnWidth',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_head_font',
			'property'   => 'rightHeadFont',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_head_bg',
			'property'   => 'rightHeadBg',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_font',
			'property'   => 'rightFont',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_bg_primary',
			'property'   => 'rightBgPrimary',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'top_head_font',
			'property'   => 'topHeadFont',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'top_head_bg',
			'property'   => 'topHeadBg',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'top_font',
			'property'   => 'topFont',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'top_bg_primary',
			'property'   => 'topBgPrimary',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'created_at',
			'property'   => 'createdAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'updated_at',
			'property'   => 'updatedAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'deleted_at',
			'property'   => 'deletedAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasJoin('permissions', array(
			'multiple'  => true,
			'mapper'    => '\\Eportfolio\\PermissionMapper',
			'this_key'  => 'page_id',
			'other_key' => 'page_id',
		));

		$this->hasSoftDeleteDateField('deleted_at');
	}

	/**
	 * Finds all the pages owned by $ownerId that $viewerId can see
	 *
	 * @param $viewerId
	 * @param $ownerId
	 * @return \Schoolbox\Collection
	 */
	public function findViewablePages($viewerId, $ownerId) {
		$viewablePages = new \Schoolbox\Collection();

		/* -_-
		 */
		$groupHandler = new \Group();

		/* The categories the viewer is involved in
		 */
		$allCategories = $groupHandler->involvedInCategoryList($viewerId);

		$categoryIds = array();

		if (is_array($allCategories) && count($allCategories)) {
			foreach($allCategories as $currentCategory) {
				$categoryIds[] = $currentCategory['id'];
			}
		}
		/* Get all the pages with their permissions
		 */
		$allPages = $this->findPagesWithPermissions($ownerId);

		foreach($allPages as $currentPage) {

			/* They can be a superuser
			 */
			$isSuperuser = $_SESSION['su'];

			/* They can be a staff member
			 */
			$isStaff = $groupHandler->isStaff();

			/* They can be the owner of the page
			 */
			$isOwner = ($currentPage->getUid() == $viewerId);

			if ($isSuperuser || $isStaff || $isOwner) {
				$viewablePages->setOffset($currentPage->getPageId(), $currentPage);
			}
			/* If there are any specific permissions, try those too
			 */
			else {
				foreach($currentPage->getPermissions() as $currentPermission) {

					/* They can be a parent (the getValue() here is a binary 1 or 0)
					 */
					$isParent = (($currentPermission->getType() == 'parent') && $currentPermission->getValue());

					/* They can be in a group
					 */
					$isInGroup = (($currentPermission->getType() == 'group') && in_array($currentPermission->getValue(), $categoryIds));

					/* They can be allowed specifically
					 */
					$isStudent = (($currentPermission->getType() == 'student') && ($currentPermission->getValue() == $viewerId));

					if ($isParent || $isInGroup || $isStudent) {
						$viewablePages->setOffset($currentPage->getPageId(), $currentPage);
					}
				}
			}
		}

		/* Reindex everything
		 */
		$viewablePages->reindex();

		return $viewablePages;
	}

	/**
	 * Finds all the pages owned by $ownerId with associated permissions
	 *
	 * @param $ownerId
	 * @return \Schoolbox\Collection
	 */
	public function findPagesWithPermissions($ownerId) {
		$permission_mapper = $this->getJoinMapper('permissions');

		$query = "
            SELECT
            	{$this->getColumnString('ep')},
            	{$permission_mapper->getColumnString('p')}
            FROM `{$this->table}` ep
            LEFT JOIN {$this->getJoinSql('permissions', 'ep', 'p')}
            WHERE ep.uid = :ownerId
            ORDER BY created_at, ep.page_id ASC
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(':ownerId', $ownerId);
		$statement->execute();

		/* Manually construct hierarchy of blog items and their comments
		 */
		$collection = new \Schoolbox\Collection();

		foreach ($statement as $row) {
			$pageItem = $this->hydrate($row, 'ep');
			$permissionItem = $permission_mapper->hydrate($row, 'p');

			/* Put it into the collection in the index with the same
			 * value as the page id, if it doesn't already live there
			 */
			if (!is_a($collection->getOffset($pageItem->getPageId()), '\Eportfolio\Page')) {
				$collection->setOffset($pageItem->getPageId(), $pageItem);
			}

			/* If a permission was parsed from this $row, hydrate
			 * put the permission with its parent page
			 */
			if (is_numeric($permissionItem->getId())) {
				$collection->getOffset($permissionItem->getPageId())->getPermissions()->add($permissionItem);
			}
		}

		/* Reindex everything
		 *
		 * foreach() can't understand it when you don't reindex to zero-based array
		 */
		$collection->reindex();

		return $collection;
	}

	/**
	 * Finds all publish targets. Currently, this means all eportfolio pages
	 * which have either a file list or image list controller.
	 *
	 * @param  int  $user_id
	 * @return array
	 */
	public function findPublishablePages($user_id) {
		$query = "
			SELECT DISTINCT {$this->getColumnString('p')}
			FROM {$this->getTable()} p
			INNER JOIN component_instance ci ON p.page_id = ci.identifier AND ci.module='eportfolio'
			INNER JOIN component_definition cd ON ci.component_definition_id = cd.id
			WHERE p.uid = :userId
			AND   p.deleted_at IS NULL
			AND   cd.class IN (
				'Component_Eportfolio_FileListController',
				'Component_Eportfolio_ImageController',
				'Component_Eportfolio_ImageListController',
				'Component_Eportfolio_VideoController'
			)
		";
		$statement = $this->getDatabase()->prepare($query);
		$statement->bindValue('userId', $user_id);
		$statement->execute();
		$return = array();
		foreach ($this->hydrateList($statement, 'p') as $page) {
			$return[$page->getPageId()] = array('page' => $page);
		}
		return $return;
	}
}
