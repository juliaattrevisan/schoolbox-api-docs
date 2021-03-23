<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
        if (!$this->isSourceRenderable() && !$this->hasContextWriteAccess()) {
            return false;
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

        $this->setBlock($templateHandle, 'has_rand_image', 'has_rand_image_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

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
            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_write', 'empty_write_block');

            $this->setVar([
                'has_rand_image_block' => '',
                'empty_read_block' => '',
                'empty_write_block' => '',
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_block', 'empty_read');
            } else {
                $this->parse('empty_write_block', 'empty_write');
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            //outputs an custom image and link to the homepage
            $this->setBlock('has_rand_image', "preview_image", "preview_image_block");
            $this->setBlock('has_rand_image', 'submit_image_randimage', 'submit_image_randimage_block');

            $this->setVar([
                'empty_state_block' => '',
                'submit_image_randimage_block' => '',
            ]);

            if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                $this->parse('submit_image_randimage_block', 'submit_image_randimage');
            }

            $image = $fileList->first();

            //get link
            $imageurl = "/cms/image.php?id={$image->getId()}&height=2048&width=2048";

            $this->setVar("PREVIEW_IMAGE_URL", $imageurl);
            $this->setVar("PREVIEW_IMAGE_DESC", Scrub::text($image->getDescription()));

            $this->parse('preview_image_block', 'preview_image');
            $this->parse('has_rand_image_block', 'has_rand_image');
        }

        return true; // everything went according to plan
    }
}
