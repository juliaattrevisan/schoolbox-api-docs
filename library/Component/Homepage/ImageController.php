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

        /**
         * Get the image
         */
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

        /**
         * Set the component heading, priority as follows..
         * - Heading set in component config
         * - Filename (if a file is going to be shown)
         * - Default 'Image'
         */
        if ($this->getConfig('Heading') != '') {
            // noop
        } else if (count($images)) {
            /** @var Schoolbox\Resource\File\Model $image */
            $image = $images->first();
            $this->setComponentName($image->getFilename());
        } else {
            $this->setComponentName('Image');
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_image', 'has_image_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        /**
         * Check if query found any image - if not then set empty state
         */
        if (count($images) == 0) {

            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_write', 'empty_write_block');

            $this->setVar([
                'has_image_block' => '',
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
            $this->setBlock('has_image', 'preview_image', "preview_image_block");
            $this->setBlock('has_image', 'submit_image', 'submit_image_block');

            $this->setVar([
                'empty_state_block' => '',
                'submit_image_block' => '',
            ]);

            if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
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
                    $this->setVar('preview_image_block', '');
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
            $this->parse("preview_image_block", "preview_image");
            $this->parse('has_image_block', 'has_image');

        }

        return true; // everything went according to plan
    }
}
