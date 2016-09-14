<?php
/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

use \Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as Folder;

abstract class Component_Dashboard_ControllerAbstract extends Component_ControllerAbstract
{
    /**
     * Sets up a dashboard component.
     *
     * @param null       $context
     */
    public function setup($context = null)
    {
        parent::setup($context);

        $this->setContextPermission(UserAccess::ACCESS_FULL);

        if($this->isUsingAltSource()){
            $accessor = \Schoolbox\Permission\Access\Access::getInstance();
            $this->setContentPermission(min($accessor->getFolderAccess($this->getUser(),[$this->getSource()->getId()]), $this->getMaximumPermission()));
        } else {
            $this->setContentPermission(UserAccess::ACCESS_FULL);
        }
    }

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // Hide Dashboard components if the folder they pull data from is soft-deleted
        return (!$this->isUsingAltSource() || !$this->getSource()->isDeleted());
    }

    protected function getTemplate($templateName = '', $templatePath = '')
    {

        if (empty($templatePath)) {
            $templatePath = "component/dashboard";
        }

        return parent::getTemplate($templateName, $templatePath);
    }

    /* Getters / Setters
     */

    /**
     * @return null
     */
    public function getContext()
    {
        return parent::getContext();
    }

    /**
     * @param null $context
     * @return static
     */
    protected function setContext($context = null)
    {
        // Dashboard components never have context
        parent::setContext(null);
        return $this;
    }

    /**
     * @return Folder|null
     */
    public function getSource()
    {
        return parent::getSource();
    }

    /**
     * @param Folder|null $source
     * @return static
     */
    public function setSource(Folder $source = null)
    {
        parent::setSource($source);
        return $this;
    }
}