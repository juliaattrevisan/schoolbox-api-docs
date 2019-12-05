<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use \Schoolbox\Permission\Access\UserAccess;

class Component_Dashboard_ImageSliderController extends Component_Dashboard_ControllerAbstract
{

    /**
     * @var Schoolbox\Resource\File\Mapper
     */
    private $fileMapper;


    /**
     * Available sort fields for this component.
     *
     * @var array
     */
    protected $sortFields = array('name', 'date');

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

        $this->setFileMapper(Schoolbox\Resource\File\Mapper::getInstance());
        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'imageSlider')) {
            return false;
        }
        $this->parseConfig();

        if ($this->getContentPermission() < UserAccess::ACCESS_READ) {
            return false;
        }

        $this->setBlock($templateHandle, 'image_scroller', 'image_scroller_block');
        $this->setBlock('image_scroller', 'linked_image', 'linked_image_block');
        $this->setBlock('image_scroller', 'unlinked_image', 'unlinked_image_block');
        $this->setBlock($templateHandle, 'has_header', 'has_header_block');

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
                'COMPONENT_ALIGN'                  => $this->getConfig('align'),
                'COMPONENT_PIXEL'                  => $pixel,
                'COMPONENT_CONFIG_TIMING_VALUE_MS' => $this->getConfig('timing')
                    ? $this->getConfig('timing') * 1000
                    : '2000',
            )
        );

        // Set up component name
        $this->setVar(
            'COMPONENT_NAME',
            strlen($this->getConfig('componentname')) ? $this->getConfig('componentname') : 'Image Slider'
        );

        /* Prevent multiple galleries from other categories stacking up in the wrong place
         * and initialise empty gallery by default
         */
        $this->setVar('image_scroller_block', '');

        // Load images for the current homepage
        $opts = [
            'filter' => 'image',
            'size' => (int)$this->getConfigLoader()->get('image_slideshow_max'),
            'sort' => $this->getConfig('sort'),
            'sortdir' => $this->getConfig('sortdir')
        ];

        $allFiles = $this->fileMapper->findForFolders([$this->getSource()->getId()], $opts);

        // Have update form to back to this homepage, not the gallery's homepage
        $this->setVar('IMAGESCROLLER_CATEGORY_ID', $this->getSource()->getId());

        // Iterate
        foreach ($allFiles as $currentFile) {
            $url = '';

            // sanitise url
            if($currentFile->getDescription()) {
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
                    'IMAGE_ID'        => $currentFile->getId(),
                    'IMAGE_NAME'      => $currentFile->getTitle(),
                    'IMAGE_URL'       => $url,
                    'IMAGE_DIMENSION' => $pixel
                )
            );

            if($url) {
                $this->parse('linked_image_block', 'linked_image');
                $this->setVar('unlinked_image_block','');
            }
            else {
                $this->parse('unlinked_image_block', 'unlinked_image');
                $this->setVar('linked_image_block','');
            }

            $this->parse('image_scroller_block', 'image_scroller', true);
        }

        return true; // everything went according to plan
    }

    /* Getters/Setters
     */


    /**
     * @return \Schoolbox\Resource\File\Mapper
     */
    public function getFileMapper()
    {
        return $this->fileMapper;
    }


    /**
     * @param \Schoolbox\Resource\File\Mapper $fileMapper
     * @return static
     */
    public function setFileMapper($fileMapper)
    {
        $this->fileMapper = $fileMapper;
        return $this;
    }

}
