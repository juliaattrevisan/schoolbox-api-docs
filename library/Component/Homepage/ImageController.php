<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use \Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File\Model as File;

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

        // Is an selected image configured to be displayed ?
        $image = null;
        if($this->getConfig('image')){
            /** @var File $image */
            $image = $this->getFileMapper()->findForFolders(
                [$this->getContext()->getId()],
                [
                    'name'      => $this->getConfig('image'),
                    'filter'    => ['image'],
                    'sort'      => 'date',
                    'getHidden' => true,
                    'size'      => 1,
                ]
            )->first();
        }

        /**
         * Set the component heading, priority as follows..
         * - Heading set in component config
         * - image.title
         * - image.filename
         * - Default 'Image'
         */
        if ($this->getConfig('Heading') == '') {
            if ($image) {
                $this->setComponentName($image->getTitle() ?: $image->getFilename());
            } else {
                $this->setComponentName('Image');
            }
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_image', 'has_image_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        // Were we able to retrieve an image to display ?
        if ($image) {
            $this->setVar('empty_state_block', '');
            $this->setBlock('has_image', 'preview_image', "preview_image_block");
            $this->setBlock('has_image', 'submit_image', 'submit_image_block');

            $pixel = 2048;
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
            if ($this->hasContextWriteAccess()) {
                $this->parse('submit_image_block', 'submit_image');
            } else {
                $this->setVar('submit_image_block', '');
            }
            $this->parse('has_image_block', 'has_image');
        }
        // No Image - render the empty state
        else {
            $this->setVar('has_image_block', '');
            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_write', 'empty_write_block');

            if ($this->getContextPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_block', 'empty_read');
                $this->setVar('empty_write_block', '');
            } else {
                $this->parse('empty_write_block', 'empty_write');
                $this->setVar('empty_read_block', '');
            }
            $this->parse('empty_state_block', 'empty_state');
        }

        return true; // everything went according to plan
    }

    /**
     * @return \Schoolbox\Mapper|Schoolbox\Resource\File\Mapper
     */
    protected function getFileMapper()
    {
        return $this->getMapperFactory()->get(Schoolbox\Resource\File\Mapper::class);
    }
}
