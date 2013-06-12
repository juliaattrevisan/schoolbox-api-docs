<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ScrollerController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var File
     */
    private $fileHandler;

    /**
     * @var Category
     */
    private $catHandler;

    /**
     * @var array
     */
    private $source;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        $this->groupHandler = new Group();
        $this->fileHandler = new File();

        $this->catHandler = new Category();

        // remove any non-numeric values from the source config
        $this->source = $this->catHandler->getByID(
            $this->getConfig('src') ?
                preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')) :
                $this->getContext('id')
        );
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'scroller')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'image_scroller', 'image_scroller_block');
        $this->setBlock('image_scroller', 'show_caption', 'show_caption_block');
        $this->setBlock($templateHandle, 'submit_image', 'submit_image_block');

        // Make sure there a config option
        $pixel = $this->getConfig('pixel') ? $this->getConfig('pixel') : 200;

        $this->setVar(
            array(
                'COMPONENT_ALIGN' => $this->getConfig('align'),
                'COMPONENT_PIXEL' => $pixel,
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
        $allFiles = $this->catHandler->getImages($this->source['id'], 0, true);

        // Have update form to back to this homepage, not the gallery's homepage
        $this->setVar('IMAGESCROLLER_CATEGORY_ID', $this->source['id']);

        // Controls such as Post Images
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_image_block", "");
        } else {
            $this->parse("submit_image_block", "submit_image");
        }

        // Iterate
        if (is_array($allFiles)) {
            foreach ($allFiles as $currentFile) {
                $this->setVar(
                    array(
                        'IMAGE_ID' => $currentFile['id'],
                        'IMAGE_NAME' => $currentFile['description'],
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

        return true; // everything went according to plan

    }

}
