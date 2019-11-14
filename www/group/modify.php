<?php

use Alaress\Mail\AbstractMailer;
use Schoolbox\IConfigLoader;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Resource\Group;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Error\Mapper as ErrorMapper;

/** @var SitePage $this */
$this->setFile(TEMPLATE_content, 'group/modify.html');
$this->setBlock(TEMPLATE_content, 'groupOwner', 'groupOwner_block');
$this->setBlock('groupOwner', 'msg_enabled_followadmin', 'msg_enabled_followadmin_block');
$this->setBlock(TEMPLATE_content, 'groupUnmanaged', 'groupUnmanaged_block');
$this->setBlock(TEMPLATE_content, 'member', 'member_block');
$this->setBlock(TEMPLATE_content, 'hide_no_members', 'hide_no_members_block');
$this->setBlock('member', 'show_photo', 'show_photo_block');
$this->setBlock('member', 'make_owner', 'make_owner_block');
$this->setBlock('member', 'msg_enabled_userfollow', 'msg_enabled_userfollow_block');
$this->setBlock('member', 'msg_enabled_usermute', 'msg_enabled_usermute_block');

$this->setBlock(TEMPLATE_content, 'msg_enabled', 'msg_enabled_block');
$this->setBlock(TEMPLATE_content, 'msg_enabled_followth', 'msg_enabled_followth_block');

$this->setBlock(TEMPLATE_content, 'mail_all', 'mail_all_block');
$this->setBlock(TEMPLATE_content, 'mail_all_form', 'mail_all_form_block');
$this->setBlock(TEMPLATE_content, 'mail_all_parents', 'mail_all_parents_block');
$this->setBlock(TEMPLATE_content, 'mail_all_parents_form', 'mail_all_parents_form_block');

$this->setBlock(TEMPLATE_content, "authorisation", "authorisation_block");
$this->setBlock("authorisation", "waiting", "waiting_block");

$this->setTitle('Modify User Group');
$this->setTab('resources');

if (is_numeric($this->arg[0])) {
    $userHandler      = new User();
    $groupHandler     = new \Group(UserAccess::SERVICE_HOMEPAGE);
    $userAccessMapper = UserAccessMapper::getInstance();
    $access           = Access::getInstance();
    $roleAccessMapper = RoleAccessMapper::getInstance();
    $folderMapper     = FolderMapper::getInstance();

    try {
        $folder = $folderMapper->findByID($this->arg[0]);
    } catch (\Exception $e) {
        error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }

    // check access
    if ($access->getFolderAccess($_SESSION['user'], $folder->getId()) <  UserAccess::ACCESS_FULL) {
        error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }

    if( $folder->getInherit() ) {
        error(ErrorMapper::ERROR_GROUP_NIAN_DENIED);
    }


    $this->setVar(
        [
            'hide_no_members_block'         => '',
            'groupOwner_block'              => '',
            'groupUnmanaged_block'          => '',
            'msg_enabled_followth_block'    => '',
            'msg_enabled_followadmin_block' => '',
            'msg_enabled_block'             => '',
        ]
    );

    $output = [
        'CATEGORY_NAME' => Scrub::text($folder->getName()),
        'CATEGORY_ID'   => $folder->getId(),
        'GROUP_TYPE_1'  => '',
        'GROUP_TYPE_2'  => '',
        'GROUP_TYPE_3'  => '',
    ];

    if ($folder->getGroup() instanceof Group) {
        $output['GROUP_TYPE_' . $folder->getGroup()
                                         ->getType()] = 'checked="checked"';
    }

    $this->setVar($output);

    $search = [
        'user_id'       => 0,
        'msg_route_key' => "service.1.{$folder->getId()}",
        'delivery'      => RoutePermission::MUTE,
    ];

    /* Group Messaging
     */
    $groupMessagesEnabled = !RerouteOverrideMapper::getInstance()
                                                  ->isRouteMuted(
                                                      "service.1.{$folder->getId()}"
                                                  );

    $output = [
        'MSG_ENABLED_CHECKED'      => '',
        'MSG_FORCE_FOLLOW_CHECKED' => '',
    ];

    if ($groupMessagesEnabled) {
        $output['MSG_ENABLED_CHECKED'] = 'checked="checked"';

        /* Discover force_follow value
         */
        $groupMapper = new \Schoolbox\Resource\GroupMapper();
        try {
            $groupSettings = $groupMapper->findOneByColumn('cid', $folder->getId());
            if ($groupSettings->getForceFollow()) {
                $output['MSG_FORCE_FOLLOW_CHECKED'] = 'checked="checked"';
            }
        } catch (\Exception $e) {
            // noop
        }
    }

    $this->setVar($output);

    /* Messaging Enabled
     */
    /** @var IConfigLoader $config */
    $config           = $this->getDi()->get('config');
    $messagingEnabled = $config->get('messaging_enabled');

    if ($messagingEnabled) {
        $this->parse('msg_enabled_block', 'msg_enabled');
        $this->parse('msg_enabled_followth_block', 'msg_enabled_followth');
    }

    // Get collection of group members (including admin)
    $memberList = $userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_READ, false);
    $hasMembers = false;
    $hasAdmin   = false;

    // Keep list of everyone to email
    $all_emails        = [];
    $email_object      = new Email();

    foreach ($memberList as $member) {
        $memberAccess = $userAccessMapper->findByColumnList([
            'uid'     => $member->getId(),
            'service' => UserAccess::SERVICE_HOMEPAGE,
            'sid'     => $folder->getId(),
        ])->first();
        $memberMayOwn = $roleAccessMapper->roleMayOwnFolders($member->getRole());

        // Store member email for 'email group members' button
        if ($member->getId() != $_SESSION['id']) { // Don't email yourself
            $all_emails[] = Scrub::text($member->getFormattedEmailDisplay());
        }

        if ($memberAccess->getAccessLevel() == UserAccess::ACCESS_FULL) {
            $this->setVar([
                'GROUP_OWNER'       => Scrub::text($member->formatName()),
                'OWNER_ID'          => Scrub::text($member->getId()),
                'OWNER_EXTERNAL_ID' => Scrub::text($member->getExternalId()),
            ]);

            $this->parse('groupOwner_block', 'groupOwner', true);

            $hasAdmin = true;
        } else {
            $hasMembers = true;

            $following = !RerouteOverrideMapper::getInstance()
                                               ->isRouteMuted(
                                                   "service.1.{$folder->getId()}",
                                                   $member->getId()
                                               );

            $userLevel = 'icon-close';
            $userIcon  = 'None';
            switch ($memberAccess->getAccessLevel()) {
                case UserAccess::ACCESS_READ:
                    $userLevel = 'Read Access';
                    $userIcon  = 'icon-view';
                    break;
                case UserAccess::ACCESS_WRITE:
                    $userLevel = 'Write Access';
                    $userIcon  = 'icon-modify';
                    break;
                case UserAccess::ACCESS_FULL:
                    $userLevel = 'Group Admin';
                    $userIcon  = 'icon-favourite';
                    break;
                default:
                    break;
            }
            $output = [
                'USER_NAME'           => $member->getFullName(),
                'MEMBER_USER_ID'      => $member->getId(),
                'USER_EXTERNAL_ID'    => Scrub::text($member->getExternalId()),
                'GROUP_CHECKED'       => $memberAccess->getAccessLevel() > 1 ? 'checked=\'checked\'' : '',
                'READ_SELECTED'       => $memberAccess->getAccessLevel() == UserAccess::ACCESS_READ ? 'selected'
                    : '',
                'WRITE_SELECTED'      => $memberAccess->getAccessLevel() == UserAccess::ACCESS_WRITE ? 'selected'
                    : '',
                'READ_CHECKED'        => $memberAccess->getAccessLevel() == UserAccess::ACCESS_READ ? 'checked'
                    : '',
                'WRITE_CHECKED'       => $memberAccess->getAccessLevel() == UserAccess::ACCESS_WRITE ? 'checked'
                    : '',
                'USER_FOLLOWING'      => $following ? 'Yes' : 'No',
                'USER_FOLLOWING_ICON' => $following ? 'icon-tick' : 'icon-mute',
                // Stylage
                'ADMIN_ALLOWED'       => $memberMayOwn ? '' : 'disabled',
                'USER_LEVEL'          => $userLevel,
                'USER_ICON'           => $userIcon,
            ];

            $this->setVar($output);

            if (count($memberList) > 100) {
                $this->setVar(['show_photo_block' => '']);
            } else {
                $this->parse('show_photo_block', 'show_photo');
            }

            $this->setVar([
                'make_owner_block'             => '',
                'msg_enabled_userfollow_block' => '',
                'msg_enabled_usermute_block'   => '',
            ]);

            if ($memberMayOwn) {
                $this->parse('make_owner_block', 'make_owner');
            }

            if ($messagingEnabled) {
                if ($following) {
                    $this->parse('msg_enabled_userfollow_block', 'msg_enabled_userfollow');
                } else {
                    $this->parse('msg_enabled_usermute_block', 'msg_enabled_usermute');
                }
            }

            $this->parse('member_block', 'member', true);
        }
    }

    if ($hasAdmin) {
        $this->parse('msg_enabled_followadmin_block', 'msg_enabled_followadmin');
    } else {
        $this->parse('groupUnmanaged_block', 'groupUnmanaged');
    }

    if ($hasMembers) {
        $this->parse('hide_no_members_block', 'hide_no_members', true);
    } else {
        $this->setVar('member_block', '');
    }

    // Set up the email blocks (PHPLib sucks)
    $this->setVar([
        'mail_all_block'              => '',
        'mail_all_form_block'         => '',
        'mail_all_parents_block'      => '',
        'mail_all_parents_form_block' => '',
    ]);

    if (\Config::get('mail_enable')) {
        $this->setVar([
            'REPLYTO'  => $email_object->formatEmailFromArray($_SESSION)
        ]);

        if (!empty($all_emails)) {
            $this->setVar([
                'EMAILALL' => implode(AbstractMailer::implodeGlue(), $all_emails),
            ]);
            $this->parse('mail_all_block', 'mail_all');
            $this->parse('mail_all_form_block', 'mail_all_form');
        }

        // Recipients for this will be generated on click, so show as long as mail is enabled
        $this->parse('mail_all_parents_block', 'mail_all_parents');
        $this->parse('mail_all_parents_form_block', 'mail_all_parents_form');
    }

    $waitingList = $groupHandler->getWaitingList($folder->getId());
    if (is_array($waitingList)) {
        foreach ($waitingList as $waiting) {
            $output = [
                'WAITING_NAME'        => $userHandler->formatName($waiting),
                'WAITING_ID'          => $waiting['uid'],
                'WAITING_EXTERNAL_ID' => Scrub::text($waiting['synergy_id']),
            ];
            $this->setVar($output);
            $this->parse("waiting_block", "waiting", true);
        }
        $this->parse("authorisation_block", "authorisation");

    } else {
        $this->setVar("authorisation_block", "");
    }

    /* Check if current user is admin, and if they are not a super user
       Warning will show if they decrease their perms
    */

    $userAccess = $userAccessMapper->findByColumnList(['uid' => $_SESSION['user']->getId(), 'sid' =>$this->arg[0], 'service' => UserAccess::SERVICE_HOMEPAGE, 'type' => UserAccess::ACCESS_FULL]);
    $admin = $userAccess->count();

    $this->setVar('ADMIN_NOT_SU', $admin && !$_SESSION['user']->isSuperuser());

    $trail = new \Schoolbox\Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text'  => $folder->getName(),
            'href'  => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text'  => 'Manage Group',
            'href'  => '',
            'class' => 'active',
        ],
    ]);
} else {
    error(2);
}
