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
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'scroller')) {
            return false;
        }


        $this->setBlock($templateHandle, 'source_folder_deleted', 'source_folder_deleted_block');
        $this->setBlock($templateHandle, 'source_folder_exists', 'source_folder_exists_block');
        $this->setBlock('source_folder_exists', 'submit_image_scroller', 'submit_image_scroller_block');
        $this->setBlock('source_folder_exists', 'image_scroller', 'image_scroller_block');
        $this->setBlock('image_scroller', 'show_caption', 'show_caption_block');
        $this->setBlock('image_scroller', 'linked_image', 'linked_image_block');
        $this->setBlock('image_scroller', 'unlinked_image', 'unlinked_image_block');

        $this->parseConfig();

        if(!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailable();
        } else {
            $this->setVar('source_folder_deleted_block', '');

            // Set up component name
            $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());


            $this->setVar(
                array(
                    'COMPONENT_CONFIG_TIMING_VALUE_MS' => $this->getConfig('timing') ? $this->getConfig('timing') * 1000 : '2000',
                    'COMPONENT_CONFIG_RANDOM_STARTING_SLIDE' => $this->getConfig('randomStartingSlide'),
                    'COMPONENT_CONFIG_IMAGE_RATIO' => $this->getConfig('imageRatio') ? $this->getConfig('imageRatio') : 'square',
                )
            );

            /* Prevent multiple galleries from other categories stacking up in the wrong place
             * and initialise empty gallery by default
             */
            $this->setVar('image_scroller_block', '');

            // Load images for the current homepage
            $allFiles = $this->getFolderMapper()->findImages($this->source, 0, true);

            if ($this->getConfig('randomStartingSlide')) {
                $this->setVar('COMPONENT_RANDOM_SLIDE', rand(1, count($allFiles)));
            } else {
                $this->setVar('COMPONENT_RANDOM_SLIDE', 1);
            }
            // Have update form to back to this homepage, not the gallery's homepage
            $this->setVar('IMAGESCROLLER_CATEGORY_ID', $this->source->getId());

            // Controls such as Post Images
            if ($this->contentPermission < WRITE_ACCESS) {
                $this->setVar("submit_image_scroller_block", "");
            } else {
                $this->parse("submit_image_scroller_block", "submit_image_scroller");
            }

            // Iterate
            foreach ($allFiles as $currentFile) {

                $url = '';

                // sanitise url
                if ($currentFile->getDescription()) {
                    $description = $currentFile->getDescription();
                    if (filter_var($description, FILTER_VALIDATE_URL)) {
                        $url = $description;
                    } // it might be referencing inside schoolbox, so simulate that to the validator
                    elseif (filter_var('http://localhost/' . $description, FILTER_VALIDATE_URL)) {
                        $url = $description;
                    }
                }

                $this->setVar(
                    array(
                        'IMAGE_ID' => $currentFile->getId(),
                        'IMAGE_NAME' => Scrub::text($currentFile->getTitle()),
                        'IMAGE_URL' => $url
                    )
                );

                /* Link image */
                if ($url) {
                    $this->parse('linked_image_block', 'linked_image');
                    $this->setVar('unlinked_image_block', '');
                } else {
                    $this->parse('unlinked_image_block', 'unlinked_image');
                    $this->setVar('linked_image_block', '');
                }

                // Display captions
                if ($this->getConfig('captionState') == 'Active') {
                    $this->parse('show_caption_block', 'show_caption');
                } else {
                    $this->setVar('show_caption_block', '');
                }

                $this->parse('image_scroller_block', 'image_scroller', true);
            }
            $this->parse('source_folder_exists_block','source_folder_exists');
        }

        return true; // everything went according to plan
    }

}
