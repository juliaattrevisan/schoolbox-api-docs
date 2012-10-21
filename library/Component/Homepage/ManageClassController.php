<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ManageClassController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

        $this->groupHandler = new Group();

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'manageClass')) {
            return false;
        }

        if($this->permission < WRITE_ACCESS) {
            return false;
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, "class_list", "class_list_block");
        $this->setBlock($templateHandle, "class_mark", "class_mark_block");

        if(Cfg::get('external_enable') == false || $this->permission < WRITE_ACCESS || $this->getContext('code') == '') {
            $this->setVar("class_list_block", "");
        }
        else {
            $this->parse("class_list_block", "class_list");
        }

        if($this->groupHandler->isStaff() && $this->groupHandler->involvedInSubject($this->getContext('code'))) {
            $this->parse("class_mark_block", "class_mark");
        }
        else {
            $this->setVar("class_mark_block", "");
        }

        return true; // everything went according to plan
    }

}
