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

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File\Mapper as FileMapper;

class Component_Dashboard_SlideshowController extends Component_Dashboard_ControllerAbstract
{
    /**
     * @var FileMapper
     */
    private FileMapper $fileMapper;

    /**
     * Available sort fields for this component.
     *
     * @var string[]
     */
    protected $sortFields = array('name', 'date');

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        if (!$this->isSourceRenderable()) {
            return false;
        }

        $this->fileMapper = FileMapper::getInstance();
        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param string $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'slideshow')) {
            return false;
        }

        if ($this->getContentPermission() < UserAccess::ACCESS_READ) {
            return false;
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, 'image_slideshow', 'image_slideshow_block');
        $this->setBlock('image_slideshow', 'linked_image', 'linked_image_block');
        $this->setBlock('image_slideshow', 'unlinked_image', 'unlinked_image_block');
        $this->setBlock('image_slideshow', 'show_caption', 'show_caption_block');
        $this->setBlock($templateHandle, 'has_header', 'has_header_block');
        $this->setBlock($templateHandle, 'image_slideshow_action_view_gallery', 'image_slideshow_action_view_gallery_block');

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

        $pixel = $this->getConfig('pixel') ?: 800;
        $imageRatio = $this->getConfig('imageRatio') ?: 'auto';

        $this->setVar(
            array(
                'COMPONENT_CONFIG_TIMING_VALUE_MS' => $this->getConfig('timing') ? $this->getConfig(
                    'timing'
                ) * 1000 : '2000',
                'COMPONENT_CONFIG_RANDOM_STARTING_SLIDE' => $this->getConfig('randomStartingSlide'),
                'COMPONENT_CONFIG_IMAGE_RATIO'          => $imageRatio,
                'COMPONENT_CONFIG_SLIDESHOWTRANSITION'  => $this->getConfig('transition') ?: 'swipe',
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
        $this->setVar('image_slideshow_block', '');

        $maxSlideshowImages = (int)$this->getConfigLoader()->get(ConfigMapper::NAME_IMAGE_SLIDESHOW_MAX);

        // Load images for the current homepage
        $opts = [
            'filter' => 'image',
            'size' => $maxSlideshowImages + 1, // +1 to check if we have more than the max
            'sort' => $this->getConfig('sort'),
            'sortdir' => $this->getConfig('sortdir')
        ];

        $allFiles = $this->fileMapper->findForFolders([$this->getSource()->getId()], $opts);
        $limitedFiles = $allFiles->limit($maxSlideshowImages);

        if ($this->getConfig('randomStartingSlide')) {
            $this->setVar('COMPONENT_RANDOM_SLIDE', rand(0, count($limitedFiles) - 1));
        } else {
            $this->setVar('COMPONENT_RANDOM_SLIDE', 0);
        }

        // Have update form to back to this homepage, not the gallery's homepage
        $this->setVar('IMAGESLIDESHOW_CATEGORY_ID', $this->getSource()->getId());

        // Iterate
        if (!$limitedFiles->count()) {
            return false; // No images to display
        }

        $this->setVar('image_slideshow_action_view_gallery_block', '');

        // Check if we have more than the max images, and show the view gallery button to everyone
        if (count($allFiles) > $maxSlideshowImages) {
            $this->parse('image_slideshow_action_view_gallery_block', 'image_slideshow_action_view_gallery');
        }

        foreach ($limitedFiles as $currentFile) {
            $url = '';

            // sanitise url
            if ($currentFile->getDescription()) {
                $description = $currentFile->getDescription();
                if (filter_var($description, FILTER_VALIDATE_URL)) {
                    $url = $description;
                } elseif (filter_var('http://localhost/' . $description, FILTER_VALIDATE_URL)) {
                    // it might be referencing inside schoolbox, so simulate that to the validator
                    $url = $description;
                }
            }

            $this->setVar(
                array(
                    'IMAGE_ID'        => $currentFile->getId(),
                    'IMAGE_NAME'      => $currentFile->getTitle(),
                    'IMAGE_URL'       => $url,
                    'IMAGE_DIMENSION' => $imageRatio === 'auto' ? $pixel : 0,
                )
            );

            // Display captions
            if ($this->getConfig('captionState') == 'Active') {
                $this->parse('show_caption_block', 'show_caption');
            } else {
                $this->setVar('show_caption_block', '');
            }

            if ($url) {
                $this->parse('linked_image_block', 'linked_image');
                $this->setVar('unlinked_image_block', '');
            } else {
                $this->parse('unlinked_image_block', 'unlinked_image');
                $this->setVar('linked_image_block', '');
            }

            $this->parse('image_slideshow_block', 'image_slideshow', true);
        }

        return true; // everything went according to plan
    }
}
