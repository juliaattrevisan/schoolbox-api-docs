<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ManagersController extends Component_Homepage_ControllerAbstract {

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'managers')) {
            return false;
        }
        $this->parseConfig();

        //output managers
        $this->setBlock($templateHandle, "staff_members", "staff_member_block");
        $this->setBlock($templateHandle, "member_email", "member_email_block");

        $userHandler = new User;
        $managerList = $userHandler->getManagers($this->getContext('id'));

        $emailObj = new Email();

        if (is_array($managerList)) {
            $j = 0;
            $emailall = array();
            foreach ($managerList as $manager) {

                $name = $userHandler->formatName($manager);

                $email = $emailObj->formatEmailFromArray($manager);

                $this->setVar("MEMBER_NAME", "<a href=\"/search/user/{$manager['uid']}\">$name</a>");

                if($manager['type'] == 3)
                    $name .= " (Group Admin.)";

                if($email) {
                    $emailall[] = $email;
                }

                $this->setVar('ON_OFF', ++$j % 2 ? '' : 'dark');
                $this->parse("staff_member_block", "staff_members", true);

            }

            $this->setVar("REPLYTO", $emailObj->formatEmailFromArray($_SESSION));

            if(count($emailall)) {
                $this->setVar("EMAILALL", implode(', ', $emailall));
            }
            else {
                $this->setVar("EMAILALL", '');
            }

            $this->parse("member_email_block", "member_email");
        } else {
            $this->setVar("staff_member_block", "");
            $this->setVar('member_email_block','');
        }

        return true; // everything went according to plan
    }

}
