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
     * @var Group
     */
    protected $groupHandler;

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
        if(!parent::loadTemplate($templateHandle, 'managers')) {
            return false;
        }
        $this->parseConfig();

        //output managers
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
        $managerList = $userHandler->getManagers($this->getContext('id'));

        $emailObj = new Email();

        if (is_array($managerList)) {
            $display = $this->getConfig('displayPortraits') ? $this->getConfig('displayPortraits') : 'grid';
            foreach ($managerList as $manager) {

                $name = $userHandler->formatName($manager);
                $email = $emailObj->formatEmailFromArray($manager);
                $meta = $userHandler->getByID($manager['uid']);

                if ($manager['type'] == 3) {
                    $name .= " (Group Admin.)";
                }

                if ($email) {
                    $emailall[] = $email;
                }

                $this->setVar(array(
                    'PERSON_UID' => $manager['uid'],
                    'PERSON_NAME' => $name,
                    'PERSON_EXTERNAL_ID' => $manager['synergy_id'],
                    'PERSON_GROUP' => $meta['gname'],
                    'LIST_STYLE' => $displayOptions[$display]['css'],
                ));

                /* Don't display images in list view
             */
                if ($display == 'grid') {
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

            /* Student bulk email
        */
            if ($this->groupHandler->isStaff() && isset($emailall)) {
                $this->setVar("REPLYTO", $emailObj->formatEmailFromArray($_SESSION));
                $this->setVar("EMAILALL", implode(',',$emailall));
                $this->parse("member_email_block", "member_email");
            }
            else {
                $this->setVar('member_email_block', '');
            }
        }
        else {
            $this->setVar("person_block", "");
            $this->setVar('member_email_block', '');
        }

        return true; // everything went according to plan
    }

}
