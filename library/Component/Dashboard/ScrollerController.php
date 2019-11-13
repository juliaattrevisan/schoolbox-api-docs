<?php
/**
 * Plag'd from Doctore's component
 *
 * This is the Image Slideshow component
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use \Schoolbox\Permission\Access\UserAccess;

class Component_Dashboard_ScrollerController extends Component_Dashboard_ControllerAbstract
{

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        if(!$this->isSourceRenderable()){
            return false;
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
        if (!parent::loadTemplate($templateHandle, 'scroller')) {
            return false;
        }

        if ($this->getContentPermission() < UserAccess::ACCESS_READ) {
            return false;
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, 'image_scroller', 'image_scroller_block');
        $this->setBlock($templateHandle, 'has_header', 'has_header_block');
        $this->setBlock('image_scroller', 'show_caption', 'show_caption_block');

        /* Hide header bar if desired
         */
        if ($this->getConfig('compactMode')) {
            $this->setVar(
                array(
                    'has_header_block' => ''
                )
            );
        } else {
            $this->parse('has_header_block', 'has_header');
        }

        $this->setVar(
            array(
                'COMPONENT_CONFIG_TIMING_VALUE_MS' => $this->getConfig('timing') ? $this->getConfig(
                    'timing'
                ) * 1000 : '2000',
                'COMPONENT_CONFIG_RANDOM_STARTING_SLIDE' => $this->getConfig('randomStartingSlide'),
                'COMPONENT_CONFIG_IMAGE_RATIO'          => $this->getConfig('imageRatio') ? $this->getConfig('imageRatio') : 'square' ,
            )
        );

        // Set up component name
        $this->setVar(
            'COMPONENT_NAME',
            strlen($this->getConfig('componentname')) ? $this->getConfig('componentname') : 'Image Slideshow'
        );

        /* Prevent multiple galleries from other categories stacking up in the wrong place
         * and initialise empty gallery by default
         */
        $this->setVar('image_scroller_block', '');

        // Load images for the current homepage
        $allFiles = $this->getFolderMapper()->findImages($this->getSource(), (int)$this->getConfigLoader()->get('image_slideshow_max'));

        if ($this->getConfig('randomStartingSlide')) {
            $this->setVar('COMPONENT_RANDOM_SLIDE', rand(0, count($allFiles) - 1));
        } else {
            $this->setVar('COMPONENT_RANDOM_SLIDE', 0);
        }

        // Have update form to back to this homepage, not the gallery's homepage
        $this->setVar('IMAGESCROLLER_CATEGORY_ID', $this->getSource()->getId());

        // Iterate
        if ($allFiles->count()) {
            foreach ($allFiles as $currentFile) {
                $this->setVar(
                    array(
                        'IMAGE_ID'        => $currentFile->getId(),
                        'IMAGE_NAME'      => $currentFile->getTitle(),
                    )
                );

                // Display captions
                if ($this->getConfig('captionState') == 'Active') {
                    $this->parse('show_caption_block', 'show_caption');
                } else {
                    $this->setVar('show_caption_block', '');
                }

                $this->parse('image_scroller_block', 'image_scroller', true);
            }
        }
        else {
            return false;
        }

        return true; // everything went according to plan
    }
}
