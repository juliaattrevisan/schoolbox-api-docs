<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ChatController extends Component_Homepage_ControllerAbstract
{

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
        if (!parent::loadTemplate($templateHandle, 'chat')) {
            return false;
        }
        $this->parseConfig();

        $group       = new Group();
        $userHandler = new User();

        $this->setBlock($templateHandle, 'chat_state_active', 'chat_state_active_block');
        $this->setBlock($templateHandle, 'chat_state_inactive', 'chat_state_inactive_block');
        $this->setBlock($templateHandle, 'chat_staff_js', 'chat_staff_js_block');

        if ($this->getConfig('state') == 'Active') {
            $this->setVar('chat_state_inactive_block', '');
            $this->parse('chat_state_active_block', 'chat_state_active');
        } else {
            $this->setVar('chat_state_active_block', '');
            if ($this->permission > 1) {
                $this->parse('chat_state_inactive_block', 'chat_state_inactive');
            } else {
                $this->setVar('chat_state_inactive_block', '');
            }
        }

        $this->setVar(
            array(
                'CHAT_COMPONENT_ID' => $this->instance->getId(),
                'CHAT_USER_ID'      => $_SESSION['id'],
                'CHAT_USER_EXT_ID'  => $_SESSION['sid'],
                'CHAT_USER_NAME'    => Scrub::text($userHandler->formatName($_SESSION)),
                'CHAT_USER_PERM'    => $this->permission,
                'CHAT_STATE'        => $this->getConfig('state'),
            )
        );

        if ($group->isStaff()) {
            $this->parse('chat_staff_js_block', 'chat_staff_js');
        } else {
            $this->setVar('chat_staff_js_block', '');
        }

        return true; // everything went according to plan
    }
}
