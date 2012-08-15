<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_MembersController extends Component_Homepage_ControllerAbstract {

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
        if(!parent::loadTemplate($templateHandle, 'members')) {
            return false;
        }
        $this->parseConfig();

        //output users of group
        $this->setBlock($templateHandle, "members", "members_block");
        $this->setBlock($templateHandle, "member_email", "member_email_block");

        $userHandler = new User;
        $userList = $userHandler->getAllInCategory($this->getContext('id'));
        $emailObj = new Email();

        if (is_array($userList)) {
            $emailall = array();
            $j = 0;
            foreach ($userList as $user) {

                $name = $userHandler->formatName($user);
                $email = $emailObj->formatEmailFromArray($user);

                if($email) {
                    $emailall[] = $email;
                }

                $this->setVar(array(
                    "MEMBER_NAME" => ($user['uid'] ? "<a href=\"/search/user/{$user['uid']}\">$name</a>" : $name) . ($user['type'] == 3 ? ' (Group Admin)' : ''),
                    'ON_OFF'      => ++$j % 2 ? '' : 'dark'
                ));

                $this->parse("members_block", "members", true);
            }

            $this->setVar(array(
                "REPLYTO"  => $emailObj->formatEmailFromArray($_SESSION),
                "EMAILALL" => implode(', ', $emailall)
            ));

            $this->parse("member_email_block", "member_email");

        }
        else {
            $this->setVar("members_block", '');
            $this->setVar('member_email_block','');
        }

        return true; // everything went according to plan
    }

}
