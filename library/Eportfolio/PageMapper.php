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
		$this->hasTable('eportfolio_config');
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
			'column'     => 'left_bg_secondary',
			'property'   => 'leftBgSecondary',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_link',
			'property'   => 'leftLink',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_hover',
			'property'   => 'leftHover',
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
			'column'     => 'right_bg_secondary',
			'property'   => 'rightBgSecondary',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_link',
			'property'   => 'rightLink',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_hover',
			'property'   => 'rightHover',
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
			'column'     => 'top_bg_secondary',
			'property'   => 'topBgSecondary',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'top_link',
			'property'   => 'topLink',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'top_hover',
			'property'   => 'topHover',
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

		parent::mapping();
	}

	/**
	 * Find all the user's pages with a particular component on it
	 *
	 * @param $userId
	 * @param $componentDefinition
	 *
	 * @return array
	 */
	public function findAvailablePages($userId, $componentDefinition) {
		$pageData = array();
		$pageIds = array();

		$allComponents = Component_InstanceMapper::getInstance()->findByDefinition('eportfolio', $componentDefinition->id);

		/* Get a list of pages which have File List components
		 */
		if (is_array($allComponents) && count($allComponents)) {
			foreach($allComponents as $currentComponent) {
				$pageIds[] = $currentComponent->identifier;
			}

			$allPages = Eportfolio_PageMapper::getInstance()->findIn('page_id', $pageIds);

			/* Then associate each page with their components
			 */
			if ($allPages->count()) {
				foreach ($allPages as $currentPage) {
					$componentList = array();

					/* Only get this user's pages
					 */
					if ($currentPage->getUid() == $userId) {
						foreach ($allComponents as $currentComponent) {
							if ($currentComponent->identifier == $currentPage->getPageId()) {
								$componentList[] = $currentComponent;
							}
						}

						/* Throw it all into some arbitrary array
						 * We're going to do this the poopy way
						 */
						$pageData[] = array(
							'page' => $currentPage,
							'componentList' => $componentList
						);
					}
				}
			}
		}

		return $pageData;
	}
}