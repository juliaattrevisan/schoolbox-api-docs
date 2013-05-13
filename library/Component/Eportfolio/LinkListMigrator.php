<?php
/**
 * BlogMigrator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Component\Eportfolio;

class LinkListMigrator extends MigratorAbstract {

	/**
	 * All links
	 *
	 * @var null
	 */
	private $allLinkLists = array();

	/**
	 * Setup
	 */
	protected function setup() {
		$this->fetchAll();
	}

	/**
	 * Migrate this component
	 */
	public function migrate() {
		if (count($this->getAllLinkLists())) {
			$newComp = new \Component_Instance();
			$newComp->definitionId = 73;
			$newComp->identifier = $this->getPageId();
			$newComp->module = 'eportfolio';
			$newComp->sequence = 0;
			$newComp->position = 'left';

			$newCompIds = \Component_InstanceMapper::getInstance()->save($newComp);

			$componentInstanceID = $newCompIds[0];

			foreach($this->getAllLinkLists() as $currentLinkList) {
				$url = new \Url($currentLinkList['url']);
				$url->prependScheme();

				$newLinkList = new \Component\Eportfolio\LinkList();
				$newLinkList->setComponentInstanceId($componentInstanceID);
				$newLinkList->setTitle($currentLinkList['description']);
				$newLinkList->setContent($url->getUrl());

				\Component\Eportfolio\LinkListMapper::getInstance()->save($newLinkList);
			}
		}
	}

	/**
	 * Fetch all the records for this component
	 */
	protected function fetchAll() {
		$query = "
			SELECT *
			FROM eportfolio_link
			WHERE owner = {$this->getUserId()}
		";

		$result = $this->getDb()->getAll($query);

		if ($result) {
			$this->setAllLinkLists($result);
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set all items
	 *
	 * @param null $allLinkLists
	 */
	private function setAllLinkLists($allLinkLists) {
		$this->allLinkLists = $allLinkLists;
	}

	/**
	 * Get all items
	 *
	 * @return null
	 */
	private function getAllLinkLists() {
		return $this->allLinkLists;
	}
}