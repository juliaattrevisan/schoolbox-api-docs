<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ImageGalleryController extends Component_Homepage_ControllerAbstract
{

    /**
     * A group handler
     *
     * @var Group
     */
    private $groupHandler;

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
        if(!parent::execute()){
            return false;
        }

        // Short Circuit: No point to load data if we can't view it
        if(!$this->isSourceRenderable()){
            return true;
        }

        $this->groupHandler = new Group();
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

        /* Template rendering
         */
        $this->setBlock($templateHandle, 'source_folder_deleted', 'source_folder_deleted_block');
        $this->setBlock($templateHandle, 'source_folder_exists', 'source_folder_exists_block');
        $this->setBlock('source_folder_exists', 'image_gallery', 'image_gallery_block');
        $this->setBlock('source_folder_exists', 'submit_image_gallery', 'submit_image_gallery_block');

        if(!$this->isSourceRenderable()) {
            if($this->hasContextWriteAccess()) {
                if(!$this->hasReadAccess()) {
                    $this->renderAltSourceDenied();
                } else if($this->getSource()->isDeleted()) {
                    $this->renderAltSourceDeleted();
                } else {
                    $this->renderEmptyState();
                }
            } else {
                return false;
            }
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
            $this->setVar(
                array(
                    'COMPONENT_NAME' => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName(),
                    'IMAGEGALLERY_CATEGORY_ID' => $this->getSource()->getId()
                )
            );

            // Controls such as Post News
            if ($this->contentPermission < WRITE_ACCESS) {
                $this->setVar(
                    array(
                        'submit_image_gallery_block' => ''
                    )
                );
            } else {
                $this->parse('submit_image_gallery_block', 'submit_image_gallery');
            }

            // Load images for the requested homepage
            $allImages = Schoolbox\Resource\File\Mapper::getInstance()->findForFolders(
                [$this->getSource()->getId()],
                [
                    'filter' => ['image'],
                    'size' => $this->getConfig('size')
                ]
            );

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
