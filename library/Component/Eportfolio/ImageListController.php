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
        // noop
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
		$this->setBlock($templateHandle, 'modify_button', 'modify_button_block');
		$this->setBlock($templateHandle, 'dropzone', 'dropzone_block');

		$this->setVar(array(
			'list_item_block' => ''
		));

		/* Show edit buttons
		 */
		if ($this->permission >= WRITE_ACCESS) {
			$this->parse('dropzone_block', 'dropzone');
			$this->parse('modify_button_block', 'modify_button');
		} else {
			$this->setVar('dropzone_block', '');
			$this->setVar('modify_button_block', '');
		}

		$mapper = new \Component\Eportfolio\FileListMapper();

		$allImages = $mapper->findAllImagesByPage($this->getInstance()->getIdentifier());

		if ($allImages->count()) {
			foreach($allImages as $currentImage) {
				$this->setVar(array(
					'IMAGE_ID' => $currentImage->getFileId(),
					'IMAGE_DESCRIPTION' => Scrub::text('')
				));

				$this->parse('list_item_block', 'list_item', true);
			}
		}

		return true;
	}
}