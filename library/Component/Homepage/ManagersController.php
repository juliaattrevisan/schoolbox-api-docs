<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;

class Component_Homepage_ManagersController extends Component_Homepage_ControllerAbstract
{
    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'members')) {
            return false;
        }
        $this->parseConfig();

        //output managers
        $this->setBlock($templateHandle, "member_email", "member_email_block");
        $this->setBlock($templateHandle, "person", "person_block");
        $this->setBlock('person', 'has_thumbnail', 'has_thumbnail_block');
        $this->setBlock('person', 'has_icon', 'has_icon_block');

        $this->setVar('has_thumbnail_block', '');
        $this->setVar('has_icon_block', '');
        $this->setVar('person_block', '');

        /* Display options
         */
        $displayOptions = array(
            'grid' => array(
                'name'  => 'Grid',
                'value' => 'grid',
                'css'   => 'grid',
            ),
            'list' => array(
                'name'  => 'List',
                'value' => 'list',
                'css'   => '',
            ),
        );

        $display = $this->getConfig('displayPortraits') ? $this->getConfig('displayPortraits') : 'grid';
        $this->setVar(
            array(
                'LIST_STYLE' => $displayOptions[$display]['css'],
            )
        );

        // Get collection of managers
        $userMapper = new UserMapper();
        $managerList = $userMapper->findByAccess(UserAccess::SERVICE_HOMEPAGE, UserAccess::ACCESS_WRITE, $this->getContext()->getId(), 100);

        // Get admin
        $userAccessMapper = new UserAccessMapper();
        $admin = $userAccessMapper->findAdminsInCategory($this->getContext())->first();

        $emailObj = new Email();

        if (!empty($managerList)) {
            foreach ($managerList as $manager) {

                $name  = $manager->getFullName();
                $email = $manager->getEmail();

                if ($manager->getId() == $admin->getId()) {
                    $name .= " (Group Admin.)";
                }

                if ($email) {
                    $emailall[] = $email;
                }

                $this->setVar(
                    array(
                        'PERSON_UID'         => $manager->getId(),
                        'PERSON_NAME'        => $name,
                        'PERSON_EXTERNAL_ID' => $manager->getExternalId(),
                        'PERSON_GROUP'       => $manager->getRole()->getName(),
                    )
                );

                /* Don't display images in list view
			  	 */
                if ($this->getConfig('displayPortraits') == 'grid') {
                    $this->setVar(array(
                        'GRID_VIEW_CLASSES' => 'medium-block-grid-2'
                    ));

                    $this->parse('has_thumbnail_block', 'has_thumbnail');
                    $this->setVar('has_icon_block', '');
                } else {
                    $this->setVar(array(
                        'GRID_VIEW_CLASSES' => ''
                    ));

                    $this->setVar('has_thumbnail_block', '');
                    $this->parse('has_icon_block', 'has_icon');
                }

                $this->parse('person_block', 'person', true);

                /* Clear children after parsing out the parent
			   	 */
                $this->setVar('has_thumbnail_block', '');
                $this->setVar('has_icon_block', '');

            }
            /* Student bulk email
        	 */
            if ($this->getUser()->getRole()->isStaff() && isset($emailall) && \Cfg::get('mail_enable')) {
                $this->setVar("REPLYTO", $emailObj->formatEmailFromArray($_SESSION));
                $this->setVar("EMAILALL", implode(',', $emailall));
                $this->parse("member_email_block", "member_email");
            } else {
                $this->setVar('member_email_block', '');
            }
        } else {
            $this->setVar("person_block", "");
            $this->setVar('member_email_block', '');
        }

        return true; // everything went according to plan
    }
}
