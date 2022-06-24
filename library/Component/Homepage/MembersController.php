<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Mail\Mailer;
use Schoolbox\Resource\Folder\NNIAN;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Profile\ValidEmailVisible;

class Component_Homepage_MembersController extends Component_Homepage_ControllerAbstract
{

    /**
     * Maximum number of members to display
     * If the collection exceeds this it will truncate the list and leave a message
     *
     * @var int
     */
    const MAX_MEMBERS = 100;

    /**
     * This appears in the empty state message
     */
    const TYPE_NAME = 'members';

    /** @var External_UserInterface $externalUser */
    private $externalUser;

    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     * @return bool
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Schoolbox\Mapper\Exception
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
        $userMapper = UserMapper::getInstance();
        if ($this->getContext()->getInherit()) {
            // Find nearest nian folder
            $folderMapper = FolderMapper::getInstance();
            $nianFolder   = NNIAN::getInstance($folderMapper)->findNearestNonInheritingAncestralNode($this->getContext());
            $userList     = $userMapper->findByAccess(
                UserAccess::SERVICE_HOMEPAGE,
                UserAccess::ACCESS_READ,
                $nianFolder->getId(),
                ['lastname' => 'asc']
            );
        } else {
            $userList = $userMapper->findByAccess(
                UserAccess::SERVICE_HOMEPAGE,
                UserAccess::ACCESS_READ,
                $this->getContext()->getId(),
                ['lastname' => 'asc']
            );
        }

        if (count($userList) === 0) {
            $this->setBlock('empty_state', 'empty_class', 'empty_class_block');
            $this->setBlock('empty_state', 'empty_not_group_members', 'empty_not_group_members_block');
            $this->setBlock('empty_state', 'empty_not_group_managers', 'empty_not_group_managers_block');
            $this->setBlock('empty_state', 'empty_members', 'empty_members_block');
            $this->setBlock('empty_state', 'empty_managers', 'empty_managers_block');

            $this->setVar(
                [
                    'has_members_block'              => '',
                    'empty_class_block'              => '',
                    'empty_not_group_members_block'  => '',
                    'empty_not_group_managers_block' => '',
                    'empty_members_block'            => '',
                    'empty_managers_block'           => '',
                    'TYPE_NAME'                      => self::TYPE_NAME,
                    'COMPONENT_ICON'                 => 'group',
                    'ALT_COMPONENT'                  => 'class list',
                    'FOLDER_ID'                      => $this->getSource()
                                                             ->getFolder()
                                                             ->getId(),
                ]
            );

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE || $this->getContext()->getInherit()) {
                return false;
            } elseif ($this->getSource()->getFolder()->getCodes()->count()) {
                $block = 'empty_class';
            } elseif (!$this->getSource()->getFolder()->isGroup()) {
                $block = 'empty_not_group_members';
            } else {
                $block = 'empty_members';
            }

            $this->parse($block . '_block', $block);
            $this->parse('empty_state_block', 'empty_state');
        } else {
            //output users of group
            $this->setBlock('has_members', "member_email", "member_email_block");
            $this->setBlock('has_members', "person", "person_block");
            $this->setBlock('has_members', 'people_hidden', 'people_hidden_block');
            $this->setBlock('person', 'photo', 'photo_block');

            $this->setVar(
                [
                    'empty_state_block'   => '',
                    'person_block'        => '',
                    'people_hidden_block' => '',
                    'MAX_MEMBERS'         => self::MAX_MEMBERS,
                ]
            );

            /* Display options
             */
            $displayOptions = [
                'grid' => [
                    'name'  => 'Grid',
                    'value' => 'grid',
                    'css'   => 'grid',
                ],
                'list' => [
                    'name'  => 'List',
                    'value' => 'list',
                    'css'   => '',
                ],
            ];

            $display = $this->getConfig('displayPortraits') ? $this->getConfig('displayPortraits') : 'grid';
            $this->setVar(
                [
                    'LIST_STYLE' => $displayOptions[$display]['css'],
                ]
            );

            $userListCount = $userList->count();

            // If the members are going to go over the max..
            // Set the people_hidden_block to show and set the number of members that are hidden
            if ($userListCount > self::MAX_MEMBERS) {
                $this->setVar('HIDDEN_COUNT', $userListCount - self::MAX_MEMBERS);
                $this->parse('people_hidden_block', 'people_hidden');
            }

            // Get admin
            $userAccessMapper = new UserAccessMapper();
            $admins           = $userAccessMapper->findAdminsInCategory($this->getContext());

            $emailObj     = new Email();
            $emailVisible = new ValidEmailVisible($this->getExternalUser(), $this->getConfigLoader());
            $emailall     = [];

            $staffPhotoVisibility = $this->getConfigLoader()->get('protect_teacher_photo');
            $studentPhotoHidden   = $this->getConfigLoader()->get('protect_student_photo');
            $viewer               = $this->getUser();

            if ($userListCount > 0) {
                $i = 0;
                foreach ($userList as $user) {
                    /* Member bulk email
                     */
                    if ($emailVisible->isSatisfiedBy($user, $viewer)) {
                        $emailall[] = Scrub::text($user->getFormattedEmailDisplay());
                    }

                    /* Member cards
                     */
                    if ($i < self::MAX_MEMBERS) {
                        $name = $user->getFullName();

                        if (count($admins)) {
                            foreach ($admins as $admin) {
                                if ($user->getId() === $admin->getId()) {
                                    $name .= " (Group Admin)";
                                }
                            }
                        }

                        $this->setVar(
                            [
                                'PERSON_UID'         => $user->getId(),
                                'PERSON_NAME'        => \Scrub::text($name),
                                'PERSON_EXTERNAL_ID' => \Scrub::text($user->getExternalId()),
                                'PERSON_GROUP'       => \Scrub::text($user->getPreferredTitle()),
                                'LIST_STYLE'         => $displayOptions[$display]['css'],
                            ]
                        );

                        /* Don't display images in list view
                         */
                        if ($this->getConfig('displayPortraits') == 'grid') {
                            $this->setVar('GRID_VIEW_CLASSES', 'medium-block-grid-2');
                        } else {
                            $this->setVar('GRID_VIEW_CLASSES', '');
                        }

                        //Hide Student Photo
                        if ($user->getRole()->isStudent()) {
                            if ($viewer->getRole()->isStudent() && $studentPhotoHidden) {
                                $this->setVar('photo_block', '');
                            } elseif ($viewer->getRole()->isParent() && !$viewer->isParentOf($user->loadParents())) {
                                $this->setVar('photo_block', '');
                            } else {
                                $this->parse('photo_block', 'photo');
                            }
                        } elseif ($user->getRole()->isStaff()) {
                            //Hide Staff Photo
                            if ($staffPhotoVisibility == 2 && $viewer->getRole()->isStudent()) {
                                $this->setVar('photo_block', '');
                            } elseif ($staffPhotoVisibility == 1 && !$viewer->getRole()->isStaff()) {
                                $this->setVar('photo_block', '');
                            } else {
                                $this->parse('photo_block', 'photo');
                            }
                        } else {
                            $this->parse('photo_block', 'photo');
                        }

                        $this->parse('person_block', 'person', true);
                    }

                    $i++;
                }

                $this->setVar("REPLYTO", $emailObj->formatEmailFromArray($_SESSION));
                $this->setVar("EMAILALL", implode(Mailer::implodeGlue(), array_unique($emailall)));

                if ($this->getConfigLoader()->get('mail_enable') && $this->getUser()->hasEmail()) {
                    $this->parse("member_email_block", "member_email");
                } else {
                    $this->setVar('member_email_block', '');
                }
            } else {
                $this->setVar('member_email_block', '');
            }

            $this->parse('has_members_block', 'has_members');
        }

        return true; // everything went according to plan
    }

    /**
     * Gets external user interface
     * @return External_UserInterface
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    private function getExternalUser(): External_UserInterface
    {
        if (!$this->externalUser) {
            $this->externalUser = $this->getSitePage()->getDi()->get('externalUser');
        }
        return $this->externalUser;
    }
}
