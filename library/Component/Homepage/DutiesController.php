<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_DutiesController extends Component_Homepage_ControllerAbstract
{

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {

        // TODO: Implement execute() method.

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'duties')) {
            return false;
        }

        $this->setComponentName('Duties for ' . date('l, jS \of F Y'));

        $this->parseConfig();

        $this->setBlock($templateHandle, "duties", "d_block");

        $dutyHandler = new Duty;

        $dutyList = $dutyHandler->getAllInCategory($this->getContext()->getId());

        $block = false;
        if (is_array($dutyList)) {
            $j = 0;
            foreach ($dutyList as $duty) {
                $this->setVar("DUTY", $duty['name']);
                $this->setVar("ASSIGNEDUSER", $duty['user']);
                $this->parse("d_block", "duties", true);
                $block = true;
                $this->setVar('ON_OFF', ++$j % 2 ? '' : 'dark');
            }
        } else {
            $this->setVar("DUTY", "No Duties set for today");
            $this->setVar("ASSIGNEDUSER", '');
            $this->parse("d_block", "duties", true);
        }
        $this->setBlock($templateHandle, "edit_duties", "edit_duties_block");

        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("edit_duties_block", "");
        } else {
            $this->parse("edit_duties_block", "edit_duties");
        }

        return true;
    }
}
