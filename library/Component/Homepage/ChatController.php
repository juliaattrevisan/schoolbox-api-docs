<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_ChatController extends Component_Homepage_ControllerAbstract
{
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

        $this->setBlock($templateHandle, 'chat', 'chat_block');
        $this->setBlock($templateHandle, 'chat_staff_js', 'chat_staff_js_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $this->setVar([
            'CHAT_COMPONENT_ID' => $this->getInstance()->getId(),
            'CHAT_USER_ID' => $this->getUser()->getId(),
            'CHAT_USER_EXT_ID' => $this->getUser()->getExternalId(),
            'CHAT_USER_NAME' => Scrub::text($this->getUser()->getFullName()),
            'CHAT_USER_PERM' => $this->getContentPermission(),
            'CHAT_STATE' => $this->getConfig('state'),
        ]);

        if ($this->getConfig('state') != 'Active') {
            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_write', 'empty_write_block');

            $this->setVar([
                'chat_block' => '',
                'chat_staff_js_block' => '',
                'empty_write_block' => '',
                'empty_read_block' => '',
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_block', 'empty_read');
            } else {
                $this->parse('empty_write_block', 'empty_write');
            }

            $this->parse('empty_state_block', 'empty_state');
        } else {
            $this->setVar([
                'empty_state_block' => '',
                'chat_staff_js_block' => ''
            ]);

            if ($this->getUser()->getRole()->isStaff()) {
                $this->parse('chat_staff_js_block', 'chat_staff_js');
            }

            $this->parse('chat_block', 'chat');
        }

        return true; // everything went according to plan
    }
}
