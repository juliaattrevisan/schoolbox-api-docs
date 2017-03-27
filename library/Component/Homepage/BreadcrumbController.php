<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
use \Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as Folder;

class Component_Homepage_BreadcrumbController extends Component_Homepage_ControllerAbstract
{
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



        $this->setBlock($templateHandle, 'source_folder_deleted', 'source_folder_deleted_block');
        $this->setBlock($templateHandle, 'source_folder_exists', 'source_folder_exists_block');
        $this->parseConfig();

        if ($this->getContentPermission() < UserAccess::ACCESS_WRITE && $this->getConfig('compactMode')) {
            /* Remove headers */
            $this->setVar('CONFIGURATION', '');
            $this->setBlock('source_folder_exists', 'header', 'header_block');
            $this->setBlock('source_folder_exists', 'dashboard_header', 'dashboard_header_block');
            $this->setVar('header_block', '');
            $this->setVar('dashboard_header_block', '');
        }

        if(!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailable();
        } else {
            $this->setVar('source_folder_deleted_block', '');




            $this->setBlock('source_folder_exists', 'category_breadcrumb', 'category_breadcrumb_block');
            $this->setVar('category_breadcrumb_block', '');

            foreach ($this->getFolderMapper()->findParents($this->getSource(), null, true) as $parent) {
                /** @var Folder $parent */
                $this->setVar('BREADCRUMB_CATEGORY_ID', $parent->getId());
                $this->setVar('BREADCRUMB_CATEGORY_NAME', $parent->getName());
                $this->setVar('BREADCRUMB_CATEGORY_CLASS', '');
                $this->parse('category_breadcrumb_block', 'category_breadcrumb', true);
            }

            $this->setVar('BREADCRUMB_CATEGORY_ID', $this->getSource()->getId());
            $this->setVar('BREADCRUMB_CATEGORY_NAME', $this->getSource()->getName());
            $this->setVar('BREADCRUMB_CATEGORY_CLASS', 'last');
            $this->parse('category_breadcrumb_block', 'category_breadcrumb', true);
            $this->parse('source_folder_exists_block','source_folder_exists');
        }

        return true; // everything went according to plan
    }
}
