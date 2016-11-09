<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use \Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_RandImageController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var DeprecatedDatabase
     */
    private $db;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if(!$this->isSourceRenderable()){
            return $this->hasContextWriteAccess();
        }

        $this->db = DeprecatedDatabase::getInstance();
        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'randImage')) {
            return false;
        }
        $this->parseConfig();



        // get image id
        $fileList = Schoolbox\Resource\File\Mapper::getInstance()->findForFolders(
            [$this->getContext()->getId()],
            array(
                'size'      => 1,
                'filter'    => ['image'],
                'sort'      => 'random',
                'getHidden' => false,
            )
        );

        if (!count($fileList)) {

            $this->setBlock($templateHandle, 'image', 'image_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
            $this->setBlock('empty_state', 'empty_modify', 'empty_modify_block');

            $this->setVar('image_block', '');

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->setVar("empty_modify_block", "");
            } else {
                $this->parse("empty_modify_block", "empty_modify");
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            //outputs an custom image and link to the homepage
            $this->setBlock($templateHandle, "previewimage", "preview_block");
            $this->setBlock($templateHandle, 'submit_image_randimage', 'submit_image_randimage_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

            $this->setVar('empty_state_block', '');

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->setVar('submit_image_randimage_block', '');
            } else {
                $this->parse('submit_image_randimage_block', 'submit_image_randimage');
            }

            $image = $fileList->first();

            //get link
            $imageurl = "/cms/image.php?id={$image->getId()}&height=1024&width=1024";

            $this->setVar("PREVIEW_IMAGE_URL", $imageurl);
            $this->setVar("PREVIEW_IMAGE_DESC", Scrub::text($image->getDescription()));

            $this->parse("preview_block", "previewimage");
        }

        return true; // everything went according to plan
    }
}
