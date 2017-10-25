<?php

use Schoolbox\GenericCollection;
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

//Get the perms for user
$userPerms = $accessMapper->findAllGroupsForUser($user);
$userPerms->indexBy("serviceIdentifier");

$natStrSort = function(Group $a, Group $b) {
    return strnatcasecmp($a->getCategory()->getName(), $b->getCategory()->getName());
};

// output free access groups
/** @var GenericCollection|Group[] $freeGroupList */
$freeGroupList = $groupMapper->findByColumn("type", Group::FREE_GROUP)->sortByCallback($natStrSort);

if ($freeGroupList->count()) {
    foreach ($freeGroupList as $free) {
        $folderId = $free->getCategory()->getId();
        $output   = [
            'GROUP_ID'      => $folderId,
            'GROUP_NAME'    => Scrub::text($free->getCategory()->getName()),
            'GROUP_CHECKED' => $userPerms[$folderId] ? 'checked=\'checked\'' : '',
        ];
        $this->setVar($output);

        $this->parse('free_list_block', 'free_list', true);
    }
} else {
    $this->setVar('free_list_block', '');
}


// output moderated groups
/** @var GenericCollection|Group[] $moderatedGroupList */
$moderatedGroupList = $groupMapper->findByColumn("type", Group::MODERATED_GROUP)->sortByCallback($natStrSort);

if ($moderatedGroupList->count()) {
    foreach ($moderatedGroupList as $moderated) {
        $folderId = $moderated->getCategory()->getId();
        $group    = $moderatedGroupList[$folderId];
        $output   = [
            'GROUP_ID'      => $folderId,
            'GROUP_NAME'    => Scrub::text($moderated->getCategory()->getName()),
            'GROUP_CHECKED' => $userPerms[$folderId] ? 'checked=\'checked\'' : '',
        ];
        $this->setVar($output);

        $this->setVar('waiting_block', '');

        if ($userPerms[$folderId] && $userPerms[$folderId]->getAccessLevel() == DENY_ACCESS) {
            $this->parse('waiting_block', 'waiting');
        }

        $this->parse('moderated_list_block', 'moderated_list', true);
    }
} else {
    $this->setVar('moderated_list_block', '');
}

