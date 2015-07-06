<?php

/**
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_BialikSearchController extends Component_Homepage_ControllerAbstract
{

    /**
     * Component name
     *
     * @var string
     */
    protected $componentName = 'Bialik Search';

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
    }

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

        $this->parseConfig();
        $this->setVar(
            'COMPONENT_NAME',
            strlen(trim($this->getConfig('componentname'))) ?
                $this->getConfig('componentname') :
                $this->getComponentName()
        );

        return true;
    }
}
