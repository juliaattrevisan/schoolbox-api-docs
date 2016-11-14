<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use \Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_ImageGalleryController extends Component_Homepage_ControllerAbstract
{
    /**
     * Template file to load
     *
     * @var string
     */
    protected $templateHandle = 'imageGallery';

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
     *
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {

        /* TODO: This should be inherited
         */
        if (!parent::loadTemplate($templateHandle, 'imageGallery')) {
            return false;
        }

        $this->parseConfig();

        // Load images for the requested homepage
        $allImages = Schoolbox\Resource\File\Mapper::getInstance()->findForFolders(
            [$this->getSource()->getId()],
            [
                'filter' => ['image'],
                'size' => $this->getConfig('size')
            ]
        );

        $this->setVar(
            array(
                'COMPONENT_NAME' => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName(),
                'IMAGEGALLERY_CATEGORY_ID' => $this->getSource()->getId()
            )
        );

        if ($allImages->count() == 0) {

            $this->setBlock($templateHandle, 'gallery', 'gallery_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
            $this->setBlock('empty_state', 'empty_modify', 'empty_modify_block');

            $this->setVar('gallery_block', '');

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->setVar("empty_modify_block", "");
            } else {
                $this->parse("empty_modify_block", "empty_modify");
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            /* Template rendering
             */
            $this->setBlock($templateHandle, 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock($templateHandle, 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock('source_folder_exists', 'image_gallery', 'image_gallery_block');
            $this->setBlock('source_folder_exists', 'submit_image_gallery', 'submit_image_gallery_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

            $this->setVar('empty_state_block', '');

            if (!$this->isSourceRenderable()) {
                return $this->renderSourceUnavailable();
            } else {
                $this->setVar('source_folder_deleted_block', '');

                /* Prevent multiple galleries from other categories stacking up in the wrong place
                 * and initialise empty gallery by default
                 */
                $this->setVar(
                    array(
                        'image_gallery_block' => ''
                    )
                );

                /* Set up component name
                 *
                 * Have update form point to the gallery's homepage, not this page (if this is from an alt source)
                 */

                // Controls such as Post News
                if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                    $this->setVar(
                        array(
                            'submit_image_gallery_block' => ''
                        )
                    );
                } else {
                    $this->parse('submit_image_gallery_block', 'submit_image_gallery');
                }

                // Iterate
                foreach ($allImages as $currentImage) {
                    $this->setVar(
                        array(
                            'THUMBNAIL_ID' => $currentImage->getId(),
                            'THUMBNAIL_DESCRIPTION' => $currentImage->getTitle(),
                            'THUMBNAIL_SIZE' => Cfg::get('thumbnail_size'),
                            'IMAGE_HEIGHT' => Cfg::get('thumbnail_size'),
                        )
                    );

                    $this->parse('image_gallery_block', 'image_gallery', true);
                }
                $this->parse('source_folder_exists_block', 'source_folder_exists');
            }
        }

        // everything went according to plan
        return true;
    }

    /* Getters/Setters
     */

    /**
     * Set template handle
     *
     * @param string $templateHandle
     */
    protected function setTemplateHandle($templateHandle)
    {
        $this->templateHandle = $templateHandle;
    }

    /**
     * Get template handle
     *
     * @return string
     */
    protected function getTemplateHandle()
    {
        return $this->templateHandle;
    }
}
