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
        $this->setBlock($templateHandle, "member_email", "member_email_block");
        $this->setBlock($templateHandle, "person", "person_block");
        $this->setBlock('person', 'has_portrait', 'has_portrait_block');
        $this->setBlock('person', 'has_group', 'has_group_block');

        $this->setVar('displayportraits_block', '');
        $this->setVar('has_portrait_block', '');
        $this->setVar('has_group_block', '');
        $this->setVar('person_block', '');

        /* Display options
         */
        $displayOptions = array(
            'grid' => array(
                'name'  => 'Grid',
                'value' => 'grid',
                'css'   => 'thumb',
            ),
            'list' => array(
                'name'  => 'List',
                'value' => 'list',
                'css'   => 'bar',
            ),
        );

        $userHandler = new User;
        $userList = $userHandler->getAllInCategory($this->getContext('id'));
        $emailObj = new Email();

        $emailall = array();
        if (is_array($userList)) {
            foreach ($userList as $user) {
                $name = $userHandler->formatName($user);
                $email = $emailObj->formatEmailFromArray($user);
                $meta = $userHandler->getByID($user['uid']);

                if ($user['type'] == 3) {
                    $name .= " (Group Admin.)";
                }

                if ($email) {
                    $emailall[] = $email;
                }

                $this->setVar(array(
                    'PERSON_UID' => $user['uid'],
                    'PERSON_NAME' => $name,
                    'PERSON_EXTERNAL_ID' => $user['synergy_id'],
                    'PERSON_GROUP' => $meta['gname'],
                    'LIST_STYLE' => $displayOptions[$this->getConfig('displayPortraits')]['css'],
                ));

                /* Don't display images in list view
                 */
                if ($this->getConfig('displayPortraits') == 'grid') {
                    $this->parse('has_portrait_block', 'has_portrait');
                    $this->parse('has_group_block', 'has_group');
                }
                else {
                    $this->setVar('has_portrait_block', '');
                    $this->setVar('has_group_block', '');
                }

                $this->parse('person_block', 'person', true);

                /* Clear children after parsing out the parent
             */
                $this->setVar('has_portrait_block', '');
                $this->setVar('has_group_block', '');
            }

            $this->setVar("REPLYTO", $emailObj->formatEmailFromArray($_SESSION));
            $this->setVar("EMAILALL", implode(',', $emailall));
            $this->parse("member_email_block", "member_email");
        }
        else {
            $this->setVar('member_email_block','');
        }

        return true; // everything went according to plan
    }

}
