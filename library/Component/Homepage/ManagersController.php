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
     * Maximum number of managers to display
     * If the collection exceeds this it will truncate the list and leave a message
     *
     * @var int
     */
    const MAX_MANAGERS = 100;

    /**
     * This appears in the empty state message
     */
    const TYPE_NAME = 'managers';

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

        // Get collection of members
        $userMapper = new UserMapper();
        $managerList = $userMapper->findByAccess(UserAccess::SERVICE_HOMEPAGE, UserAccess::ACCESS_WRITE, $this->getContext()->getId(), Array ('lastname' => 'asc'));

        if (count($managerList) == 0) {

            $this->setBlock($templateHandle, 'members', 'members_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

            $this->setVar([
                'TYPE_NAME' => self::TYPE_NAME,
                'members_block' => ''
            ]);

            $this->parse('empty_state_block', 'empty_state');

        } else {

            //output users of group
            $this->setBlock($templateHandle, "member_email", "member_email_block");
            $this->setBlock($templateHandle, "person", "person_block");
            $this->setBlock($templateHandle, 'people_hidden', 'people_hidden_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
            $this->setBlock('person', 'has_thumbnail', 'has_thumbnail_block');
            $this->setBlock('person', 'has_icon', 'has_icon_block');

            $this->setVar('empty_state_block', '');
            $this->setVar('has_thumbnail_block', '');
            $this->setVar('has_icon_block', '');
            $this->setVar('person_block', '');
            $this->setVar('people_hidden_block', '');
            $this->setVar('MAX_MEMBERS', self::MAX_MANAGERS);

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


            $managerListCount = $managerList->count();

            // If the members are going to go over the max..
            // Set the people_hidden_block to show and set the number of members that are hidden
            if ($managerListCount > self::MAX_MANAGERS) {
                $this->setVar('HIDDEN_COUNT', $managerListCount - self::MAX_MANAGERS);
                $this->parse('people_hidden_block', 'people_hidden');
            }

            // Get admin
            $userAccessMapper = new UserAccessMapper();
            $admin = $userAccessMapper->findAdminsInCategory($this->getContext())->first();

            if ($managerListCount > 0) {
                $i = 0;
                foreach ($managerList as $manager) {

                    /* Manager bulk email
                     */
                    $email = $manager->getEmail();

                    if ($email) {
                        $emailall[] = $email;
                    }

                    if ($this->getUser()->getRole()->isStaff() && isset($emailall) && \Cfg::get('mail_enable')) {
                        $this->setVar("REPLYTO", $this->getUser()->getEmail());
                        $this->setVar("EMAILALL", implode(',', $emailall));
                        $this->parse("member_email_block", "member_email");
                    } else {
                        $this->setVar('member_email_block', '');
                    }

                    /* Manager cards
                     */
                    if ($i < self::MAX_MANAGERS) {

                        $name = $manager->getFullName();

                        if ($admin !== null && $manager->getId() === $admin->getId()) {
                            $name .= " (Group Admin)";
                        }

                        $this->setVar(
                            array(
                                'PERSON_UID'         => $manager->getId(),
                                'PERSON_NAME'        => \Scrub::text($name),
                                'PERSON_EXTERNAL_ID' => \Scrub::text($manager->getExternalId()),
                                'PERSON_GROUP'       => \Scrub::text(empty($manager->getPositionTitle()) ? $manager->getRole()->getName() : $manager->getPositionTitle()),
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

                    $i++;
                }

            } else {
                $this->setVar("person_block", "");
                $this->setVar('member_email_block', '');
            }
        }

        return true; // everything went according to plan
    }
}
