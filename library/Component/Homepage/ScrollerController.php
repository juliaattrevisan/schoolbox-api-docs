<?php
use Schoolbox\Resource\Folder;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ScrollerController extends Component_Homepage_ControllerAbstract
{

    /**
     * An instance of Category
     *
     * @var \Schoolbox\Resource\Category
     */
    private $source;

    /**
     * Category Mapper
     *
     * @var Folder\Mapper
     */
    private $folderMapper = null;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->setFolderMapper(new Folder\Mapper());

        $this->source = $this->getContext();

        if (is_numeric($this->getConfig('src'))) {
            try {
                // remove any non-numeric values from the source config
                $this->source = $this->getFolderMapper()->findById(preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')));
            } catch (\Exception $e) {
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

        if ($this->permission < READ_ACCESS) {
            return false;
        }

        $this->setBlock($templateHandle, 'image_scroller', 'image_scroller_block');
        $this->setBlock('image_scroller', 'show_caption', 'show_caption_block');
        $this->setBlock($templateHandle, 'submit_image', 'submit_image_block');

        $this->setVar(
            array(
                'COMPONENT_CONFIG_TIMING_VALUE_MS'       => $this->getConfig('timing') ? $this->getConfig(
                            'timing'
                        ) * 1000 : '2000',
                'COMPONENT_CONFIG_RANDOM_STARTING_SLIDE' => $this->getConfig('randomStartingSlide')
            )
        );

        // Set up component name
        $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());

        /* Prevent multiple galleries from other categories stacking up in the wrong place
         * and initialise empty gallery by default
         */
        $this->setVar('image_scroller_block', '');

        // Load images for the current homepage
        $allFiles = $this->getFolderMapper()->findImages($this->source, 0, true);


        if($this->getConfig('randomStartingSlide')) {
            $this->setVar('COMPONENT_RANDOM_SLIDE', rand(1, count($allFiles)));
        }
        else {
            $this->setVar('COMPONENT_RANDOM_SLIDE', 1);
        }
        // Have update form to back to this homepage, not the gallery's homepage
        $this->setVar('IMAGESCROLLER_CATEGORY_ID', $this->source->getId());

        // Controls such as Post Images
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_image_block", "");
        } else {
            $this->parse("submit_image_block", "submit_image");
        }

        // Iterate
        foreach ($allFiles as $currentFile) {
            $this->setVar(
                array(
                    'IMAGE_ID'        => $currentFile->getId(),
                    'IMAGE_NAME'      => Scrub::text($currentFile->getTitle()),
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

        /* Need name injected here
         */
        $this->parseConfig();

        return true; // everything went according to plan
    }

    /* Getters/Setters
     */

    /**
     * Set category mapper
     *
     * @param Folder\Mapper $folderMapper
     */
    private function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }

    /**
     * Get category mapper
     *
     * @return Folder\Mapper
     */
    private function getFolderMapper()
    {
        return $this->folderMapper;
    }
}
