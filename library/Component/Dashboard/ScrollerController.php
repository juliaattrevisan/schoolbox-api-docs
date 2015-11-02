<?php

/**
 * Plag'd from Doctore's component
 *
 * This is the Image Slideshow component
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Dashboard_ScrollerController extends Component_Dashboard_ControllerAbstract
{

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * Category Mapper
     *
     * @var \Schoolbox\Resource\CategoryMapper
     */
    private $categoryMapper = null;

    /**
     * A category
     *
     * @var \Schoolbox\Resource\Category
     */
    private $source;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->groupHandler = new Group();
        $this->setCategoryMapper(Schoolbox\Resource\Folder\Mapper::getInstance());

        $this->source = $this->getCategoryMapper()->createModel();

        if (is_numeric($this->getConfig('src'))) {
            try {
                // remove any non-numeric values from the source config
                $this->source = $this->getCategoryMapper()->findById(preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')));
            } catch (\Exception $e) {
                // noop
            }
        }
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
        $this->parseConfig();

        if ($this->groupHandler->getPermission($this->source->getId()) < READ_ACCESS) {
            return false;
        }

        $this->setBlock($templateHandle, 'image_scroller', 'image_scroller_block');
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

        // Make sure there a config option
        $pixel = $this->getConfig('pixel') ? $this->getConfig('pixel') : 200;

        $this->setVar(
            array(
                'COMPONENT_PIXEL'                  => $pixel,
                'COMPONENT_CONFIG_TIMING_VALUE_MS' => $this->getConfig('timing') ? $this->getConfig(
                    'timing'
                ) * 1000 : '2000',
            )
        );

        // Set up component name
        $this->setVar(
            'COMPONENT_NAME',
            strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Image Slideshow'
        );

        /* Prevent multiple galleries from other categories stacking up in the wrong place
         * and initialise empty gallery by default
         */
        $this->setVar('image_scroller_block', '');

        // Load images for the current homepage
        $allFiles = $this->getCategoryMapper()->findImages($this->source, 0, true);

        // Have update form to back to this homepage, not the gallery's homepage
        $this->setVar('IMAGESCROLLER_CATEGORY_ID', $this->source->getId());

        // Iterate
        if ($allFiles->count()) {
            foreach ($allFiles as $currentFile) {
                $this->setVar(
                    array(
                        'IMAGE_ID'        => $currentFile->getId(),
                        'IMAGE_NAME'      => $currentFile->getDescription(),
                        'IMAGE_DIMENSION' => $pixel
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

    /* Getters/Setters
     */

    /**
     * Set category mapper
     *
     * @param Schoolbox\Resource\Folder\Mapper $categoryMapper
     */
    private function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }

    /**
     * Get category mapper
     *
     * @return Schoolbox\Resource\Folder\Mapper
     */
    private function getCategoryMapper()
    {
        return $this->categoryMapper;
    }
}
