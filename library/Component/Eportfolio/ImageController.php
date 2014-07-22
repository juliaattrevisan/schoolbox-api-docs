<?php
/**
 * Component_Eportfolio_ImageController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Component_Eportfolio_ImageController extends Component_Homepage_ControllerAbstract {

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
		if (!parent::loadTemplate($templateHandle, 'image')) {
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

		$this->setVar("IMAGE_ALIGN", $this->getConfig('align'));
		$this->setVar("PREVIEW_IMAGE_URL", "/cms/image.php?id={$image['id']}&height=$pixel&width=$pixel");
		$this->setVar("PREVIEW_IMAGE_DESC", Scrub::text($image['comment']));

	}
}