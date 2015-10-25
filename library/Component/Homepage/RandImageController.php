<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_RandImageController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var DeprecatedDatabase
     */
    private $db;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->db = DeprecatedDatabase::getInstance();
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

        //outputs an custom image and link to the homepage
        $this->setBlock($templateHandle, "previewimage", "preview_block");

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

        if (count($fileList)) {
            $image = $fileList->first();

            //get link
            $imageurl = "/cms/image.php?id={$image->getId()}&height=1024&width=1024";

            $this->setVar("PREVIEW_IMAGE_URL", $imageurl);
            $this->setVar("PREVIEW_IMAGE_DESC", Scrub::text($image->getDescription()));

            $this->parse("preview_block", "previewimage");
        } else {
            $this->setVar("preview_block", "");
        }

        return true; // everything went according to plan
    }
}
