<?php

use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Permission\Access\UserAccessMapper;

$this->setFile(TEMPLATE_content, 'group/join.html');
$this->setBlock(TEMPLATE_content, 'free_list', 'free_list_block');
$this->setBlock(TEMPLATE_content, 'moderated_list', 'moderated_list_block');
$this->setBlock('moderated_list', 'waiting', 'waiting_block');

$this->setTitle('Manage Groups');
$this->setTab('resources');

$groupMapper  = GroupMapper::getInstance();
$folderMapper = Folder\Mapper::getInstance();
$accessMapper = UserAccessMapper::getInstance();

/** @var \Schoolbox\User $user */
$user = $_SESSION["user"];

// Fetch the logged in user's write access groups to exclude from the lists.
$userWriteGroupIds = [];
$userGroups = $accessMapper->findAllForUser($user);
$userWriteGroups = $userGroups->filter(function (UserAccess $element) {
    return $element->getAccessLevel() >= UserAccess::ACCESS_WRITE;
});
/** @var $userWriteGroups UserAccess[] */
foreach ($userWriteGroups as $userGroup) {
    $userWriteGroupIds[] = $userGroup->getServiceIdentifier();
}

// Get the perms for user - including DENY (semantically PENDING) access
/** @var GenericCollection|UserAccess[] $userPerms */
$userPerms = $accessMapper->findByColumnList([
    'uid'     => $user->getId(),
    'service' => UserAccess::SERVICE_HOMEPAGE,
])->indexBy('serviceIdentifier');


$natStrSort = function (Group $a, Group $b) {
    return strnatcasecmp($a->getFolder()->getName(), $b->getFolder()->getName());
};

// output free access groups
/** @var GenericCollection|Group[] $freeGroupList */
$freeGroupList = $groupMapper->findByColumn("type", Group::FREE_GROUP)->sortByCallback($natStrSort);

$this->setVar('free_list_block', '');
if ($freeGroupList->count()) {
    foreach ($freeGroupList as $free) {
        $folderId = $free->getFolder()->getId();
        if (!in_array($folderId, $userWriteGroupIds)) {
            $output   = [
                'GROUP_ID'      => $folderId,
                'GROUP_NAME'    => Scrub::text($free->getFolder()->getName()),
                'GROUP_CHECKED' => $userPerms[$folderId] ? 'checked=\'checked\'' : '',
            ];
            $this->setVar($output);

            $this->parse('free_list_block', 'free_list', true);
        }
    }
}


// output moderated groups
/** @var GenericCollection|Group[] $moderatedGroupList */
$moderatedGroupList = $groupMapper->findByColumn("type", Group::MODERATED_GROUP)->sortByCallback($natStrSort);

$this->setVar('moderated_list_block', '');
if ($moderatedGroupList->count()) {
    foreach ($moderatedGroupList as $moderated) {
        $folderId = $moderated->getFolder()->getId();
        if (!in_array($folderId, $userWriteGroupIds)) {
            $group    = $moderatedGroupList[$folderId];
            $output   = [
                'GROUP_ID'      => $folderId,
                'GROUP_NAME'    => Scrub::text($moderated->getFolder()->getName()),
                'GROUP_CHECKED' => $userPerms[$folderId] ? 'checked=\'checked\'' : '',
            ];
            $this->setVar($output);

            $this->setVar('waiting_block', '');

            if ($userPerms[$folderId] && $userPerms[$folderId]->getAccessLevel() == UserAccess::ACCESS_DENY) {
                $this->parse('waiting_block', 'waiting');
            }

            $this->parse('moderated_list_block', 'moderated_list', true);
        }
    }
}
