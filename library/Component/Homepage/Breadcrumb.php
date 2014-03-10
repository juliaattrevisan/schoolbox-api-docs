<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Component_Homepage_Breadcrumb extends Component_Homepage_ControllerAbstract {

    /**
     * @var Group
     */
    private $catHandler;


    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        $this->catHandler = new Category();
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'breadcrumb')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'category_breadcrumb', 'category_breadcrumb_block');

        $this->setVar('category_breadcrumb_block', '');
        if ($this->category instanceof Category) {
            foreach (CategoryMapper::getInstance()->findParents($this->category) as $parent) {
                /** @var Category $parent */
                $this->setVar('PARENT_CATEGORY_ID',   $parent->getId());
                $this->setVar('PARENT_CATEGORY_NAME', $parent->getName());
                $this->parse('category_breadcrumb_block', 'category_breadcrumb', true);
            }
        }

        return true; // everything went according to plan

    }

}
