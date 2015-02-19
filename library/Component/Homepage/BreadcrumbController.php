<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;

class Component_Homepage_BreadcrumbController extends Component_Homepage_ControllerAbstract
{


    /**
     * Holds source category object
     * @var Category
     */
    private $category;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $categoryId = is_numeric($this->getConfig('src')) && $this->getConfig('src') > 0 ? $this->getConfig('src') : $this->getContext()->getId();

        if ($categoryId > 0) {
            try {
                $this->category = CategoryMapper::getInstance()->findById($categoryId);
            } catch (Exception $e) {
                // if alternate source cannot be found revert to yourself
                try {
                    $this->category = CategoryMapper::getInstance()->findById($this->getContext()->getId());
                } catch (Exception $e) {
                    return false;
                }
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

        $this->setBlock($templateHandle, 'category_breadcrumb', 'category_breadcrumb_block');

        $this->setVar('category_breadcrumb_block', '');
        if ($this->category instanceof Category) {

            foreach (CategoryMapper::getInstance()->findParents($this->category) as $parent) {
                /** @var Category $parent */
                $this->setVar('BREADCRUMB_CATEGORY_ID', $parent->getId());
                $this->setVar('BREADCRUMB_CATEGORY_NAME', $parent->getName());
                $this->setVar('BREADCRUMB_CATEGORY_CLASS', '');
                $this->parse('category_breadcrumb_block', 'category_breadcrumb', true);
            }

            $this->setVar('BREADCRUMB_CATEGORY_ID', $this->category->getId());
            $this->setVar('BREADCRUMB_CATEGORY_NAME', $this->category->getName());
            $this->setVar('BREADCRUMB_CATEGORY_CLASS', 'last');
            $this->parse('category_breadcrumb_block', 'category_breadcrumb', true);
        }

        return true; // everything went according to plan

    }

}
