<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Mail\AbstractMailer;
use Schoolbox\Resource\Folder\NNIAN;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;


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

        if($this->getContext()->getInherit()) {
            // Find nearest nian folder
            $folderMapper = FolderMapper::getInstance();
            $nianFolder = NNIAN::getInstance($folderMapper)->findNearestNonInheritingAncestralNode($this->getContext());
            $managerList   = $userMapper->findByAccess(
                UserAccess::SERVICE_HOMEPAGE,
                UserAccess::ACCESS_WRITE,
                $nianFolder->getId(),
                ['lastname' => 'asc']);
        }
        else {
            $managerList   = $userMapper->findByAccess(
                UserAccess::SERVICE_HOMEPAGE,
                UserAccess::ACCESS_WRITE,
                $this->getContext()->getId(),
                ['lastname' => 'asc']);
        }

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

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE || $this->getContext()->getInherit() ) {
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
            $this->setBlock('person', 'photo', 'photo_block');

            $this->setVar([
                'empty_state_block' => '',
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
            $admins = $userAccessMapper->findAdminsInCategory($this->getContext());

            $staffPhotoVisibility = $this->getConfigMapper()->get('protect_teacher_photo');
            $studentPhotoHidden  = $this->getConfigMapper()->get('protect_student_photo');
            $viewer = $this->getUser();

            $i = 0;
            foreach ($managerList as $manager) {

                /* Manager bulk email
                 */
                if ($manager->getEmail()) {
                    $emailall[] = Scrub::text($manager->getFormattedEmailDisplay());
                }

                if ($this->getUser()->getRole()->isStaff() && isset($emailall) && $this->getConfigMapper()->get('mail_enable')) {
                    $this->setVar([
                        "REPLYTO" => $this->getUser()->getEmail(),
                        "EMAILALL" => implode(AbstractMailer::implodeGlue(), array_unique($emailall)),
                    ]);
                    $this->parse("member_email_block", "member_email");
                } else {
                    $this->setVar('member_email_block', '');
                }

                /* Manager cards
                 */
                if ($i < self::MAX_MANAGERS) {

                    $name = $manager->getFullName();

                    if (count($admins)) {
                        foreach ($admins as $admin) {
                            if ($manager->getId() === $admin->getId()) {
                                $name .= " (Group Admin)";
                            }
                        }
                    }


                    $this->setVar([
                        'PERSON_UID'         => $manager->getId(),
                        'PERSON_NAME'        => \Scrub::text($name),
                        'PERSON_EXTERNAL_ID' => \Scrub::text($manager->getExternalId()),
                        'PERSON_GROUP'       => \Scrub::text($manager->getPreferredTitle()),
                    ]);

                    /* Don't display images in list view
                     */
                    if ($this->getConfig('displayPortraits') == 'grid') {
                        $this->setVar([
                            'GRID_VIEW_CLASSES' => 'medium-block-grid-2'
                        ]);
                    } else {
                        $this->setVar([
                            'GRID_VIEW_CLASSES' => '',
                        ]);
                    }

                    if($manager->getRole()->isStudent()) {
                        //Hide Student Photo
                        if ($viewer->getRole()->isStudent() && $studentPhotoHidden) {
                            $this->setVar('photo_block', '');
                        } elseif ($viewer->getRole()->isParent() && !$viewer->isParentOf($manager->loadParents())) {
                            $this->setVar('photo_block', '');
                        } else {
                            $this->parse('photo_block', 'photo');
                        }
                    }
                    else if($manager->getRole()->isStaff()) {
                        //Hide Staff Photo
                        if ($staffPhotoVisibility == 2 && $viewer->getRole()->isStudent()) {
                            $this->setVar('photo_block', '');
                        } elseif ($staffPhotoVisibility == 1 && !$viewer->getRole()->isStaff()) {
                            $this->setVar('photo_block', '');
                        } else {
                            $this->parse('photo_block', 'photo');
                        }
                    }
                    else {
                        $this->parse('photo_block', 'photo');
                    }

                    $this->parse('person_block', 'person', true);
                }

                $i++;
            }

            $this->parse('has_members_block', 'has_members');

        }

        return true; // everything went according to plan
    }
}
