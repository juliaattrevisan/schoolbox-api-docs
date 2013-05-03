<?php
/**
 * Component_Eportfolio_ImageListController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Component_Eportfolio_ImageListController extends Component_Eportfolio_ControllerAbstract {

	/**
	 * A mapper instance, for polymorphism
	 *
	 * @var null
	 */
	protected $mapperClass = null;

	/**
	 * Prepares data for the render function. Business logic.
	 *
	 * Override this in subclasses
	 *
	 * @return void
	 */
	public function execute() {
		return true;
	}

	/**
	 * Outputs HTML template defined by config variable 'template'
	 *
	 * @param String $templateHandle
	 * @return bool
	 */
	public function renderTemplate($templateHandle) {
		if (!parent::loadTemplate($templateHandle, $this->getConfig('template'))) {
			return false;
		}

		$this->parseConfig();

		/* Set up blocks and stuff
		 */
		$this->setBlock($templateHandle, 'list_item', 'list_item_block');

		$this->setVar(array(
			'list_item_block' => ''
		));

		$mapper = new \Component\Eportfolio\FileListMapper();

		$allImages = $mapper->findAll();

		if ($allImages->count()) {
			foreach($allImages as $currentImage) {
				$this->setVar(array(
					'IMAGE_ID' => $currentImage->getFileId(),
				));

				$this->parse('list_item_block', 'list_item', true);
			}
		}

		return true;
	}
}