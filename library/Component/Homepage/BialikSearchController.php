<?php

use Schoolbox\Permission\Access\UserAccess;

/**
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Homepage_BialikSearchController extends Component_Homepage_ControllerAbstract
{
    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'bialikSearch')) {
            return false;
        }

        $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());

        $this->parseConfig();

        /* Decide whether to show Component Title using !showComponentTitle
         * Inverted from "Hide headings from readonly users" via compactMode
         */
        if ($this->getContentPermission() < UserAccess::ACCESS_WRITE && !$this->getConfig('showComponentTitle')) {
            $this->setVar('CONFIGURATION', '');
            $this->setVar('header_block', '');
            $this->setVar('dashboard_header_block', '');
        }

        return true;
    }
}
