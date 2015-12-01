<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ImageController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var DeprecatedDatabase
     */
    private $db;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return true
     */
    public function execute()
    {
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

        //outputs an custom image and link to the homepage
        $this->setBlock($templateHandle, 'previewimage', 'preview_block');

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

        if (count($images)) {
            $image = $images->first();
        } else {
            // First non-hidden image
            $images = Schoolbox\Resource\File\Mapper::getInstance()->findForFolders(
                [$this->getContext()->getId()],
                [
                    'filter'    => ['image'],
                    'sort'      => 'date',
                    'getHidden' => false,
                    'size'      => 1,
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

        /* Need image title to be injected here
         */
        $this->parseConfig();

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


        //setup block for submission button
        $this->setBlock($templateHandle, 'submit_image', 'submit_image_block');
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar('submit_image_block', '');
        } else {
            $this->parse('submit_image_block', 'submit_image');
        }

        return true; // everything went according to plan
    }
}
