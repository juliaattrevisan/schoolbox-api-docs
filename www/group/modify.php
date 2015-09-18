<?php


use Schoolbox\Config\ConfigMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;

$this->setFile(TEMPLATE_content, 'group/modify.html');
$this->setBlock(TEMPLATE_content, 'groupOwner', 'groupOwner_block');
$this->setBlock('groupOwner', 'msg_enabled_followadmin', 'msg_enabled_followadmin_block');
$this->setBlock(TEMPLATE_content, 'groupUnmanaged', 'groupUnmanaged_block');
$this->setBlock(TEMPLATE_content, 'member', 'member_block');
$this->setBlock('member', 'make_owner', 'make_owner_block');
$this->setBlock('member', 'msg_enabled_userfollow', 'msg_enabled_userfollow_block');
$this->setBlock('member', 'msg_enabled_usermute', 'msg_enabled_usermute_block');

$this->setBlock(TEMPLATE_content, 'msg_enabled', 'msg_enabled_block');

$this->setBlock(TEMPLATE_content, 'msg_enabled_followth', 'msg_enabled_followth_block');

$this->setBlock(TEMPLATE_content, 'mail_all', 'mail_all_block');

$this->setBlock(TEMPLATE_content, "authorisation", "authorisation_block");
$this->setBlock("authorisation", "waiting", "waiting_block");

$this->setTitle('Modify User Group');
$this->setTab('resources');

$groupHandler = new Group(HOMEPAGE_SERVICE);
$categoryMapper = new \Schoolbox\Resource\CategoryMapper();

if (is_numeric($this->arg[0])) {
    $userHandler = new User();

    try {
        $category = $categoryMapper->findByID($this->arg[0]);
    } catch (\Exception $e) {
        error(19);
    }

    // check access
    if ($groupHandler->getPermission($this->arg[0]) < FULL_ACCESS) {
        error(19);
    }

    $this->setVar(
        array(
            'groupOwner_block'              => '',
            'groupUnmanaged_block'          => '',
            'msg_enabled_followth_block'    => '',
            'msg_enabled_followadmin_block' => '',
            'msg_enabled_block'             => ''
        )
    );

    $output = array('CATEGORY_NAME'		=> Scrub::text($category->getName()),
                    'CATEGORY_ID'		=> $category->getId(),
                    'GROUP_TYPE_1'		=> '',
                    'GROUP_TYPE_2'		=> '',
                    'GROUP_TYPE_3'		=> '',
                    'GROUP_TYPE_'.$category->getGroup()->getType()	=> 'checked="checked"',
                    );

    $this->setVar($output);

    $search = array(
        'user_id'        => 0,
        'msg_route_key'  => "service.1.{$category->getId()}",
        'outgoing_level' => 0
    );

    /* Group Messaging
     */
    $groupMessagesEnabled = !RerouteOverrideMapper::getInstance()->isRouteMuted(
        "service.1.{$category->getId()}"
    );

    $output = array(
        'MSG_ENABLED_CHECKED'      => '',
        'MSG_FORCE_FOLLOW_CHECKED' => ''
    );

    if ($groupMessagesEnabled) {
        $output['MSG_ENABLED_CHECKED'] = 'checked="checked"';

        /* Discover force_follow value
         */
        $groupMapper = new \Schoolbox\Resource\GroupMapper();
        try {
            $groupSettings = $groupMapper->findOneByColumn('cid', $category->getId());
            if ($groupSettings->getForceFollow()) {
                $output['MSG_FORCE_FOLLOW_CHECKED'] = 'checked="checked"';
            }
        } catch (\Exception $e) {
            // noop
        }
    }

    $this->setVar($output);

    /* Find folder owner (full access users)
     */
    $userAccessMapper = new \Schoolbox\Permission\Access\UserAccessMapper();
    try {
        $folderOwner = $userAccessMapper->findOneByColumnList(
            array(
                'sid' => $category->getId(),
                'service' => HOMEPAGE_SERVICE,
                'type' => \Schoolbox\Permission\Access\UserAccess::ACCESS_FULL
            )
        );
        // hydrate user -_-
        $folderOwner->setUser(\Schoolbox\UserMapper::getInstance()->findById($folderOwner->getUser()->getId()));
    } catch (\Exception $e) {
        $folderOwner = null;
    }

    if ($folderOwner instanceof \Schoolbox\Permission\Access\UserAccess) {
        $this->setVar(
            array(
                'GROUP_OWNER'       => Scrub::text($folderOwner->getUser()->formatName()),
                'OWNER_ID'          => Scrub::text($folderOwner->getUser()->getId()),
                'OWNER_EXTERNAL_ID' => Scrub::text($folderOwner->getUser()->getExternalId()),
            )
        );
        $this->parse('msg_enabled_followadmin_block', 'msg_enabled_followadmin');
        $this->parse('groupOwner_block', 'groupOwner');
    } else {
        $this->parse('groupUnmanaged_block', 'groupUnmanaged');
    }

    /* Messaging Enabled
     */
    $messagingEnabled = ConfigMapper::getInstance()->get('messaging_enabled');

    if ($messagingEnabled) {
        $this->parse('msg_enabled_block', 'msg_enabled');
        $this->parse('msg_enabled_followth_block', 'msg_enabled_followth');
    }

    $memberList   = $groupHandler->getMemberList($category->getId());
    //
    // Keep list of everyone to email
    $all_emails   = array();
    $email_object = new Email();
    if (is_array($memberList)) {
        foreach ($memberList as $member) {
            //
            // Don't email yourself
            if ($member['uid'] != $_SESSION['id']) {
                $all_emails[] = $email_object->formatEmailFromArray($member);
            }

            $search = array(
                'user_id'        => array(0, $member['uid']),
                'msg_route_key'  => "service.1.{$category->getId()}",
                'outgoing_level' => 0
            );

            $following = !RerouteOverrideMapper::getInstance()->isRouteMuted(
                "service.1.{$category->getId()}",
                $member['uid']
            );

            $output = array(
                'USER_NAME'           => $userHandler->formatName($member),
                'USER_JAVA'           => addslashes($userHandler->formatName($member)),
                'USER_ID'             => $member['uid'],
                'USER_EXTERNAL_ID'    => Scrub::text($member['synergy_id']),
                'GROUP_CHECKED'       => $member['type'] > 1 ? 'checked=\'checked\'' : '',
                'READ_SELECTED'       => $member['type'] == UserAccess::ACCESS_READ ? 'selected' : '',
                'WRITE_SELECTED'      => $member['type'] == UserAccess::ACCESS_WRITE ? 'selected' : '',
                'USER_FOLLOWING'      => $following ? 'Yes' : 'No',
                'USER_FOLLOWING_ICON' => $following ? 'icon-tick' : 'icon-mute',
                // Stylage
                'ADMIN_ALLOWED'       => $groupHandler->allowOwner($member['uid']) ? '' : 'disabled',
            );

            $this->setVar($output);


            $this->setVar(array(
                'make_owner_block'             => '',
                'msg_enabled_userfollow_block' => '',
                'msg_enabled_usermute_block'   => '',
            ));

            if ($groupHandler->allowOwner($member['uid'])) {
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

    } else {
        $this->setVar('member_block', '');
    }

    //
    // Set up the email all block (PHPLib sucks)
    if (!empty($all_emails) && \Cfg::get('mail_enable')) {
        $this->setVar(array(
            'REPLYTO'  => $email_object->formatEmailFromArray($_SESSION),
            'EMAILALL' => implode(',', $all_emails),
        ));
        $this->parse('mail_all_block', 'mail_all');
    } else {
        $this->setVar('mail_all_block', '');
    }

    $waitingList = $groupHandler->getWaitingList($category->getId());
    if (is_array($waitingList)) {
        foreach ($waitingList as $waiting) {
            $output = array(
                'WAITING_NAME'        => $userHandler->formatName($waiting),
                'WAITING_ID'          => $waiting['uid'],
                'WAITING_EXTERNAL_ID' => Scrub::text($waiting['synergy_id']),
            );
            $this->setVar($output);
            $this->parse("waiting_block", "waiting", true);
        }
        $this->parse("authorisation_block", "authorisation");

    } else {
        $this->setVar("authorisation_block", "");
    }

    /* The currently-logged in user and the current category owner
	 * Use for comparing and summoning a warning message that you
	 * could lose control
	 */
    $this->setVar(array(
        'MAKEADMIN_CURRENT_USER_ID' => $_SESSION['user']->getId(),
        'MAKEADMIN_CURRENT_ADMIN_ID' => $folderOwner instanceof \Schoolbox\Permission\Access\UserAccess
            ? $folderOwner->getUser()->getId()
            : '',
        'MAKEADMIN_SUPER' => isset($_SESSION['su']) ? $_SESSION['su'] : 0
    ));

    $trail = new \Schoolbox\Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $category->getName(),
            'href' => "/homepage/{$category->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Manage Group',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} else {
    error(2);
}
