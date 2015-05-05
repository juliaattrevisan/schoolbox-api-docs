<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Dashboard_ImageSliderController extends Component_Dashboard_ControllerAbstract
{

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var Schoolbox\Resource\File\Mapper
     */
    private $fileMapper;

    /**
     * Category Mapper
     *
     * @var Schoolbox\Resource\Folder\Mapper
     */
    private $categoryMapper = null;

    /**
     * A category
     *
     * @var Schoolbox\Resource\Folder\Model
     */
    private $source;

    /**
     * Available sort fields for this component.
     *
     * @var array
     */
    protected $sortFields = array('name', 'date');

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->groupHandler = new Group();
        $this->setFileMapper(Schoolbox\Resource\File\Mapper::getInstance());
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
        if (!parent::loadTemplate($templateHandle, 'imageSlider')) {
            return false;
        }
        $this->parseConfig();

        if ($this->groupHandler->getPermission($this->source->getId()) < READ_ACCESS) {
            return false;
        }

        $this->setBlock($templateHandle, 'image_scroller', 'image_scroller_block');
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
            strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Image Slider'
        );

        /* Prevent multiple galleries from other categories stacking up in the wrong place
         * and initialise empty gallery by default
         */
        $this->setVar('image_scroller_block', '');

        // Load images for the current homepage
        $allFiles = $this->getFileMapper()->findForFolders(
            [ $this->source->getId() ], array(
                'filter'  => array('image'),
                'sort'    => $this->getConfig('sort'),
                'sortdir' => $this->getConfig('sortdir'),
            )
        );

        // Have update form to back to this homepage, not the gallery's homepage
        $this->setVar('IMAGESCROLLER_CATEGORY_ID', $this->source->getId());

        // Iterate
        if (is_array($allFiles)) {
            foreach ($allFiles as $currentFile) {
                $url = '#';

                // sanitise url
                if (filter_var($currentFile['comment'], FILTER_VALIDATE_URL)) {
                    $url = $currentFile['comment'];
                } // it might be referencing inside schoolbox, so simulate that to the validator
                elseif (filter_var('http://localhost/' . $currentFile['comment'], FILTER_VALIDATE_URL)) {
                    $url = $currentFile['comment'];
                }

                $this->setVar(
                    array(
                        'IMAGE_ID'        => $currentFile['id'],
                        'IMAGE_NAME'      => $currentFile['description'],
                        'IMAGE_URL'       => $url,
                        'IMAGE_DIMENSION' => $pixel
                    )
                );

                $this->parse('image_scroller_block', 'image_scroller', true);
            }
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
