<?php
/**
 * Component_Eportfolio_ImageController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use \Schoolbox\Permission\Access\UserAccess;

/**
 * @method \Schoolbox\Resource\My\File\Mapper getMapper()
 */
class Component_Eportfolio_ImageController extends Component_Eportfolio_ControllerAbstract
{
    /**
     * @return bool
     */
    public function execute()
    {
        $this->setMapper($this->getMapperFactory()->get(\Schoolbox\Resource\My\File\Mapper::class));
        return true;
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
			$file = $this->getMapper()->findById($this->getConfig('eportfolioImage'));

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
		if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
			$this->parse('dropzone_block', 'dropzone');
			$this->parse('modify_button_block', 'modify_button');
		} else {
			$this->setVar('dropzone_block', '');
			$this->setVar('modify_button_block', '');
		}
		return true;
	}
}
