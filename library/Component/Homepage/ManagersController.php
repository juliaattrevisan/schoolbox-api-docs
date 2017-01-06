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

        $this->setBlock($templateHandle, 'has_members', 'has_members_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        // Get collection of members
        $userMapper = new UserMapper();
        $managerList = $userMapper->findByAccess(UserAccess::SERVICE_HOMEPAGE, UserAccess::ACCESS_WRITE, $this->getContext()->getId(), Array ('lastname' => 'asc'));

        if (count($managerList) == 0) {

            $this->setBlock('empty_state', 'empty_read_members', 'empty_read_members_block');
            $this->setBlock('empty_state', 'empty_read_managers', 'empty_read_managers_block');
            $this->setBlock('empty_state', 'empty_class', 'empty_class_block');
            $this->setBlock('empty_state', 'empty_not_group_members', 'empty_not_group_members_block');
            $this->setBlock('empty_state', 'empty_not_group_managers', 'empty_not_group_managers_block');
            $this->setBlock('empty_state', 'empty_members', 'empty_members_block');
            $this->setBlock('empty_state', 'empty_managers', 'empty_managers_block');

            $this->setVar([
                'has_members_block' => '',
                'empty_read_members_block' => '',
                'empty_read_managers_block' => '',
                'empty_class_block' => '',
                'empty_not_group_members_block' => '',
                'empty_not_group_managers_block' => '',
                'empty_members_block' => '',
                'empty_managers_block' => '',
                'TYPE_NAME' => self::TYPE_NAME,
                'COMPONENT_ICON' => 'group-managers',
                'ALT_COMPONENT' => 'class teachers',
                'FOLDER_ID' => $this->getSource()->getFolder()->getId(),
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $block = 'empty_read_managers';
            } elseif ($this->getSource()->getFolder()->getCodes()->count()) {
                $block = 'empty_class';
            } elseif (!$this->getSource()->getFolder()->isGroup()) {
                $block = 'empty_not_group_managers';
            } else {
                $block = 'empty_managers';
            }

            $this->parse($block . '_block', $block);
            $this->parse('empty_state_block', 'empty_state');

        } else {

            //output users of group
            $this->setBlock('has_members', "member_email", "member_email_block");
            $this->setBlock('has_members', "person", "person_block");
            $this->setBlock('has_members', 'people_hidden', 'people_hidden_block');
            $this->setBlock('person', 'has_thumbnail', 'has_thumbnail_block');
            $this->setBlock('person', 'has_icon', 'has_icon_block');

            $this->setVar([
                'empty_state_block' => '',
                'has_thumbnail_block' => '',
                'has_icon_block' => '',
                'person_block' => '',
                'people_hidden_block' => '',
                'MAX_MEMBERS' => self::MAX_MANAGERS,
            ]);


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
            $this->setVar([
                'LIST_STYLE' => $displayOptions[$display]['css'],
            ]);


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

            $i = 0;
            foreach ($managerList as $manager) {

                /* Manager bulk email
                 */
                $email = $manager->getEmail();

                if ($email) {
                    $emailall[] = $email;
                }

                if ($this->getUser()->getRole()->isStaff() && isset($emailall) && \Cfg::get('mail_enable')) {
                    $this->setVar([
                        "REPLYTO" => $this->getUser()->getEmail(),
                        "EMAILALL" => implode(',', $emailall),
                    ]);
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

                    $this->setVar([
                        'PERSON_UID'         => $manager->getId(),
                        'PERSON_NAME'        => \Scrub::text($name),
                        'PERSON_EXTERNAL_ID' => \Scrub::text($manager->getExternalId()),
                        'PERSON_GROUP'       => \Scrub::text(empty($manager->getPositionTitle()) ? $manager->getRole()->getName() : $manager->getPositionTitle()),
                    ]);

                    /* Don't display images in list view
                     */
                    if ($this->getConfig('displayPortraits') == 'grid') {
                        $this->setVar([
                            'GRID_VIEW_CLASSES' => 'medium-block-grid-2'
                        ]);

                        $this->parse('has_thumbnail_block', 'has_thumbnail');
                        $this->setVar('has_icon_block', '');
                    } else {
                        $this->setVar([
                            'GRID_VIEW_CLASSES' => '',
                            'has_thumbnail_block' => '',
                        ]);

                        $this->parse('has_icon_block', 'has_icon');
                    }

                    $this->parse('person_block', 'person', true);

                    /* Clear children after parsing out the parent
                     */
                    $this->setVar([
                        'has_thumbnail_block' => '',
                        'has_icon_block' => '',
                    ]);
                }

                $i++;
            }

            $this->parse('has_members_block', 'has_members');

        }

        return true; // everything went according to plan
    }
}
