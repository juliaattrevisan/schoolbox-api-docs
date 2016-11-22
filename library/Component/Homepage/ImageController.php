<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use \Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_ImageController extends Component_Homepage_ControllerAbstract
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
        if (!parent::loadTemplate($templateHandle, 'image')) {
            return false;
        }


        /* Need image title to be injected here
         */
        $this->parseConfig();

        // Get the image
        $images = Schoolbox\Resource\File\Mapper::getInstance()->findForFolders(
            [$this->getContext()->getId()],
            [
                'name'      => $this->getConfig('image'),
                'filter'    => ['image'],
                'sort'      => 'date',
                'getHidden' => true,
                'size'      => 1,
            ]
        );

        if (count($images) == 0) {

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
            $this->setBlock($templateHandle, 'previewimage', 'preview_block');
            $this->setBlock($templateHandle, 'submit_image', 'submit_image_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

            $this->setVar("empty_state_block", "");

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->setVar('submit_image_block', '');
            } else {
                $this->parse('submit_image_block', 'submit_image');
            }

            if (count($images)) {
                $image = $images->first();
            } else {
                // First non-hidden image
                $images = Schoolbox\Resource\File\Mapper::getInstance()->findForFolders(
                    [$this->getContext()->getId()],
                    [
                        'filter' => ['image'],
                        'sort' => 'date',
                        'getHidden' => false,
                        'size' => 1,
                    ]
                );
                if (count($images)) {
                    $image = $images->first();
                } else {
                    $this->setComponentName('Image');

                    $this->setVar('preview_block', '');

                    /* Parse the empty/default title even if no images are present/chosen
                     */
                    $this->parseConfig();
                    return true;
                }
            }
            /** @var Schoolbox\Resource\File\Model $image */
            $this->setComponentName($image->getTitle());

            $pixel = 1024;

            $this->setVar("PREVIEW_IMAGE_URL", "/cms/image.php?id={$image->getId()}&height=$pixel&width=$pixel");
            $this->setVar("PREVIEW_IMAGE_DESC", Scrub::text($image->getDescription()));

            $url = new Url($this->getConfig('url'));

            if ($url->isUrl()) {
                $this->setVar("CONTENT_URL_START", "<a href=\"" . $url->getBase64EncodedRedirect() . "\" target=\"_blank\">");
                $this->setVar("CONTENT_URL_END", "</a>");
            } else {
                $this->setVar("CONTENT_URL_START", "");
                $this->setVar("CONTENT_URL_END", "");
            }
            $this->parse("preview_block", "previewimage");
        }

        return true; // everything went according to plan
    }
}
