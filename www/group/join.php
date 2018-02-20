<?php

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;

$this->setFile(TEMPLATE_content, 'group/join.html');
$this->setBlock(TEMPLATE_content, 'free_list', 'free_list_block');
$this->setBlock(TEMPLATE_content, 'moderated_list', 'moderated_list_block');
$this->setBlock('moderated_list', 'waiting', 'waiting_block');

$this->setTitle('Manage Groups');
$this->setTab('resources');

$groupHandler = new Group(HOMEPAGE_SERVICE);
$accessMapper = UserAccessMapper::getInstance();

// Fetch the logged in user's write access groups to exclude from the lists.
$userWriteGroupIds = [];
$userGroups = $accessMapper->findAllForUser($_SESSION['user']);
$userWriteGroups = $userGroups->filter(function($element) {
    /** @var $element UserAccess */
    if ($element->getAccessLevel() >= UserAccess::ACCESS_WRITE) {
        return true;
    }
    return false;
});
foreach ($userWriteGroups as $userGroup) {
    /** @var $userGroup UserAccess */
    $userWriteGroupIds[] = $userGroup->getServiceIdentifier();
}


// output free access groups
$this->setVar('free_list_block', '');
$freeList = $groupHandler->getAllByType(GROUP_TYPE_FREE);
if (is_array($freeList)) {
    foreach ($freeList as $free) {
        if (!in_array($free['id'], $userWriteGroupIds)) {
            $output = [
                'GROUP_ID'      => $free['id'],
                'GROUP_NAME'    => Scrub::text($free['name']),
                'GROUP_CHECKED' => is_numeric($free['accessType']) ? 'checked=\'checked\'' : '',
            ];
            $this->setVar($output);

            $this->parse('free_list_block', 'free_list', true);
        }
    }
}


// output moderated groups
$this->setVar('moderated_list_block', '');
$moderatedList = $groupHandler->getAllByType(GROUP_TYPE_MODERATED);
if (is_array($moderatedList)) {
    foreach ($moderatedList as $moderated) {
        if (!in_array($moderated['id'], $userWriteGroupIds)) {
            $output = [
                'GROUP_ID'      => $moderated['id'],
                'GROUP_NAME'    => Scrub::text($moderated['name']),
                'GROUP_CHECKED' => is_numeric($moderated['accessType']) ? 'checked=\'checked\'' : '',
            ];
            $this->setVar($output);

            $this->setVar('waiting_block', '');
            if (is_numeric($moderated['accessType']) && $moderated['accessType'] == DENY_ACCESS) {
                $this->parse('waiting_block', 'waiting');
            }

            $this->parse('moderated_list_block', 'moderated_list', true);
        }
    }
}
