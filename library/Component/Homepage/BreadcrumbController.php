<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Component_Homepage_BreadcrumbController extends Component_Homepage_ControllerAbstract
{

    /**
     * Category Mapper
     *
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private $categoryMapper = null;

    /**
     * @var array
     */
    private $source;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->setCategoryMapper(new \Schoolbox\Resource\Folder\Mapper());

        $this->source = $this->getContext();

        if (is_numeric($this->getConfig('src'))) {
            try {
                // remove any non-numeric values from the source config
                $this->source = $this->getCategoryMapper()->findById(preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')));
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
        if (!parent::loadTemplate($templateHandle, 'breadcrumb')) {
            return false;
        }

        $this->parseConfig();

        if ($this->permission < WRITE_ACCESS && $this->getConfig('compactMode') ) {
            /* Remove headers */
            $this->setVar('CONFIGURATION', '');
            $this->setBlock($templateHandle, 'header', 'header_block');
            $this->setBlock($templateHandle, 'dashboard_header', 'dashboard_header_block');
            $this->setVar('header_block', '');
            $this->setVar('dashboard_header_block', '');
        }


        $this->setBlock($templateHandle, 'category_breadcrumb', 'category_breadcrumb_block');
        $this->setVar('category_breadcrumb_block', '');

        foreach ($this->getCategoryMapper()->findParents($this->source) as $parent) {
            /** @var Category $parent */
            $this->setVar('BREADCRUMB_CATEGORY_ID', $parent->getId());
            $this->setVar('BREADCRUMB_CATEGORY_NAME', $parent->getName());
            $this->setVar('BREADCRUMB_CATEGORY_CLASS', '');
            $this->parse('category_breadcrumb_block', 'category_breadcrumb', true);
        }

        $this->setVar('BREADCRUMB_CATEGORY_ID', $this->source->getId());
        $this->setVar('BREADCRUMB_CATEGORY_NAME', $this->source->getName());
        $this->setVar('BREADCRUMB_CATEGORY_CLASS', 'last');
        $this->parse('category_breadcrumb_block', 'category_breadcrumb', true);

        return true; // everything went according to plan
    }

    /* Getters/Setters
     */

    /**
     * Set category mapper
     *
     * @param FolderMapper $categoryMapper
     */
    private function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }

    /**
     * Get category mapper
     *
     * @return FolderMapper
     */
    private function getCategoryMapper()
    {
        return $this->categoryMapper;
    }
}
