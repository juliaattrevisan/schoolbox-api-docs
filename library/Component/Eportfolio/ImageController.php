<?php
/**
 * Component_Eportfolio_ImageController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Component_Eportfolio_ImageController extends Component_Eportfolio_ControllerAbstract {

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
		if (!parent::loadTemplate($templateHandle, 'Image')) {
			return false;
		}

		$this->parseConfig();

		if ($this->getConfig('pixel') > 600) {
			$pixel = 600;
		}
		else if($this->getConfig('pixel') < 20) {
			$pixel = 20;
		}
		else {
			$pixel = $this->getConfig('pixel');
		}

		/* Set up blocks and stuff
		 */
		$this->setBlock($templateHandle, 'modify_button', 'modify_button_block');
        $this->setBlock($templateHandle, 'image', 'image_block');
		$this->setBlock($templateHandle, 'dropzone', 'dropzone_block');

		/* Load a file, if one exists - otherwise clear phplibs
		 */
		try {
			$fileMapper = new \Schoolbox\Resource\My\File\Mapper();
			$file = $fileMapper->findById($this->getConfig('eportfolioImage'));

			$this->setVar(array(
				'PREVIEW_IMAGE_URL' => "/cms/myImage.php?id={$file->getId()}&height=$pixel&width=$pixel",
				'PREVIEW_IMAGE_DESC' => Scrub::text($file->getTitle())
			));
            $this->parse('image_block', 'image');
		}
		catch (\Exception $e) {
			$this->setVar('image_block', '');
		}

		/* Alignment
		 */
		$this->setVar(array(
			'IMAGE_ALIGN' => $this->getConfig('align'),
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
	}
}