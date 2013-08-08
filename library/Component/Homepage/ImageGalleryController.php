<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ImageGalleryController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var Category
     */
    private $categoryHandler;

    /**
     * @var array
     */
    private $source;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        define('IMAGES_PER_ROW', 4);

        $this->groupHandler = new Group();
        $this->categoryHandler = new Category();

        $this->source = $this->categoryHandler->getByID(
            $this->getConfig('src') ?
                $this->getConfig('src') :
                $this->getContext('id')
        );

	    if($this->source === false) {
		    $this->source = $this->catHandler->getByID($this->getContext('id'));
	    }
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

	    if($this->groupHandler->getPermission($this->source['id']) < READ_ACCESS) {
		    return false;
	    }

	    $this->setBlock($templateHandle, 'image_gallery', 'image_gallery_block');
        $this->setBlock($templateHandle, "submit_image", "submit_image_block");

        $limit = is_numeric($this->getConfig('size')) ? $this->getConfig('size') * IMAGES_PER_ROW : 0;

        // Set up component name
        $this->setVar('COMPONENT_NAME', strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Image Gallery');

        /* Prevent multiple galleries from other categories stacking up in the wrong place
        * and initialise empty gallery by default
        */
        $this->setVar('image_gallery_block', '');

        // Load images for the requested homepage
        $imageList = $this->categoryHandler->getImages($this->source['id'], $limit, true);

        // Have update form point to the gallery's homepage, not this page (if this is from an alt source)
        $this->setVar('IMAGEGALLERY_CATEGORY_ID', $this->source['id']);

        // Controls such as Post News
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_image_block", "");
        }
        else {
            $this->parse("submit_image_block", "submit_image");
        }

        // Iterate
        if (is_array($imageList)) {
            foreach($imageList as $thumbnail) {
                $this->setVar(array(
                    'THUMBNAIL_ID' => $thumbnail['id'],
                    'THUMBNAIL_DESCRIPTION' => $thumbnail['description'],
                    'THUMBNAIL_SIZE' =>	Cfg::get('thumbnail_size'),
                    'IMAGE_HEIGHT' => Cfg::get('thumbnail_size'),
                ));

                $this->parse('image_gallery_block', 'image_gallery', true);
            }
        }

        return true; // everything went according to plan
    }

}
