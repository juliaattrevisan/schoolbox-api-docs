<?php
/**
 * Migrate
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Eportfolio;

class Migrate {

	/**
	 * An old db handler
	 *
	 * @var null
	 */
	private $db = null;

	/**
	 * All the users with eportfolio components somewhere
	 *
	 * @var array
	 */
	private $allUsers = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setDb(\DeprecatedDatabase::getInstance());

		$this->fetchAllUsers();
	}

	/**
	 * Gets all the users who have eportfolio components somewhere
	 * This should orphan data that is no longer being accessed
	 */
	private function fetchAllUsers() {
		$query = "
			SELECT uid FROM eportfolio_comp_location
			GROUP BY uid
			ORDER BY uid
		";

		$result = $this->getDb()->getAll($query);

		if ($result) {
			$allUserIds = array();

			foreach($result as $current) {
				$allUserIds[] = $current['uid'];
			}

			$this->setAllUsers($allUserIds);
		}
	}

	/**
	 * Migrate everyone's stuff
	 */
	public function migrateAll() {
		if (count($this->getAllUsers())) {
			foreach($this->getAllUsers() as $currentUser) {
				$this->migrate($currentUser);
			}
		}

		exit();
	}

	/**
	 * Migrate into new components
	 */
	private function migrate($userId) {
		$oldTemplateId = 1;

		/* Create page and apply components (a page_id is generated)
		 */
		$eportfolioHandler = new \Eportfolio($userId, null);
		$eportfolioHandler->createEportfolioFromTemplate('Old ePortfolio', $oldTemplateId);

		$allNewComponents = array(
//			new \Component\Eportfolio\BlogMigrator($eportfolioHandler->getUserId(), $eportfolioHandler->getPageId()),
//			new \Component\Eportfolio\LinkListMigrator($eportfolioHandler->getUserId(), $eportfolioHandler->getPageId()),
//			new \Component\Eportfolio\FileListMigrator($eportfolioHandler->getUserId(), $eportfolioHandler->getPageId()),
//			new \Component\Eportfolio\ObjectiveMigrator($eportfolioHandler->getUserId(), $eportfolioHandler->getPageId()),
			new \Component\Eportfolio\TextboxMigrator($eportfolioHandler->getUserId(), $eportfolioHandler->getPageId()),
		);

		foreach ($allNewComponents as $migrator) {
			$migrator->migrate();
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set all users
	 *
	 * @param array $allUsers
	 */
	private function setAllUsers($allUsers) {
		$this->allUsers = $allUsers;
	}

	/**
	 * Get all users
	 *
	 * @return array
	 */
	private function getAllUsers() {
		return $this->allUsers;
	}

	/**
	 * Set db
	 *
	 * @param null $db
	 */
	private function setDb($db) {
		$this->db = $db;
	}

	/**
	 * Get db
	 *
	 * @return null
	 */
	private function getDb() {
		return $this->db;
	}
}
