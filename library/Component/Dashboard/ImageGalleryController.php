<?php
/**
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Dashboard_ImageGalleryController extends Component_Dashboard_ControllerAbstract {

    /**
     * @var Group
     */
    private $groupHandler;

	/**
	 * An instance of MyFileMapper
	 *
	 * @var null
	 */
	private $myFileMapper = null;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        define('IMAGES_PER_ROW', 4);

        $this->groupHandler = new Group();
		$this->setMyFileMapper(new \Schoolbox\MyFileMapper());
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'imageGallery')) {
            return false;
        }
        $this->parseConfig();

	    $this->setBlock($templateHandle, 'image_gallery', 'image_gallery_block');

        $limit = is_numeric($this->getConfig('size')) ? $this->getConfig('size') * IMAGES_PER_ROW : 0;

        // Set up component name
        $this->setVar('COMPONENT_NAME', strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Image Gallery');

        /* Prevent multiple galleries from other categories stacking up in the wrong place
        * and initialise empty gallery by default
        */
        $this->setVar('image_gallery_block', '');

        // Load images for the requested homepage
        $allImages = $this->getMyFileMapper()->findAllImagesByUser($_SESSION['user']->getId());

        // Iterate
		foreach($allImages as $currentImage) {
			$this->setVar(array(
				'THUMBNAIL_ID' => $currentImage->getId(),
				'THUMBNAIL_DESCRIPTION' => $currentImage->getDescription(),
				'THUMBNAIL_SIZE' =>	$this->getConfig('pixel'),
				'IMAGE_HEIGHT' => $this->getConfig('pixel'),
			));

			$this->parse('image_gallery_block', 'image_gallery', true);
		}

        return true; // everything went according to plan
    }

	/* Getters/Setters
	 */

	/**
	 * Set my file mapper
	 *
	 * @param null $myFileMapper
	 */
	private function setMyFileMapper($myFileMapper) {
		$this->myFileMapper = $myFileMapper;
	}

	/**
	 * Get my file mapper
	 *
	 * @return null
	 */
	private function getMyFileMapper() {
		return $this->myFileMapper;
	}
}
