<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use \Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\Resource\File\Model;

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
        if(!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailableTwig($templateHandle);
        }

        $this->parseConfig();

        // Load images for the current homepage
        $allFiles = $this->getFolderMapper()->findImages($this->getSource(), (int)$this->getConfigLoader()->get('image_slideshow_max'));

        // Sort in a human way
        $allFiles = $allFiles->sortByCallback( function (Model $a, Model $b) {return strnatcasecmp($a->getTitle(), $b->getTitle());} );

        // Have update form to back to this homepage, not the gallery's homepage
        $this->setVar('IMAGESCROLLER_CATEGORY_ID', $this->getSource()->getId());

        $this->setBlock($templateHandle, 'has_images', 'has_images_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        if (count($allFiles) == 0) {

            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_write', 'empty_write_block');

            $this->setVar([
                'has_images_block' => '',
                'empty_read_block' => '',
                'empty_write_block' => '',
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_block', 'empty_read');
            } else {
                $this->parse('empty_write_block', 'empty_write');
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            $this->setBlock('has_images', 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock('has_images', 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock('source_folder_exists', 'submit_image_scroller', 'submit_image_scroller_block');
            $this->setBlock('source_folder_exists', 'image_scroller', 'image_scroller_block');
            $this->setBlock('image_scroller', 'show_caption', 'show_caption_block');
            $this->setBlock('image_scroller', 'linked_image', 'linked_image_block');
            $this->setBlock('image_scroller', 'unlinked_image', 'unlinked_image_block');

            $this->setVar('empty_state_block', '');

            if (!$this->isSourceRenderable()) {
                return $this->renderSourceUnavailable();
            } else {
                $this->setVar('source_folder_deleted_block', '');

                // Set up component name
                $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());


                $this->setVar([
                    'COMPONENT_CONFIG_TIMING_VALUE_MS' => $this->getConfig('timing') ? $this->getConfig('timing') * 1000 : '2000',
                    'COMPONENT_CONFIG_RANDOM_STARTING_SLIDE' => $this->getConfig('randomStartingSlide'),
                    'COMPONENT_CONFIG_IMAGE_RATIO' => $this->getConfig('imageRatio') ? $this->getConfig('imageRatio') : 'square',
                ]);

                /* Prevent multiple galleries from other categories stacking up in the wrong place
                 * and initialise empty gallery by default
                 */
                $this->setVar('image_scroller_block', '');

                if ($this->getConfig('randomStartingSlide')) {
                    $this->setVar('COMPONENT_RANDOM_SLIDE', rand(0, count($allFiles) - 1));
                } else {
                    $this->setVar('COMPONENT_RANDOM_SLIDE', 0);
                }

                // Controls such as Post Images
                if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
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

                    $this->setVar([
                        'IMAGE_ID' => $currentFile->getId(),
                        'IMAGE_NAME' => Scrub::text($currentFile->getTitle()),
                        'IMAGE_URL' => $url,
                    ]);

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
                $this->parse('source_folder_exists_block', 'source_folder_exists');
            }

            $this->parse('has_images_block', 'has_images');
        }

        return true; // everything went according to plan
    }

}
