<?php

$this->setFile(TEMPLATE_content, 'group/join.html');
$this->setBlock(TEMPLATE_content, 'free_list', 'free_list_block');
$this->setBlock(TEMPLATE_content, 'moderated_list', 'moderated_list_block');
$this->setBlock('moderated_list', 'waiting', 'waiting_block');

$this->setTitle('Manage Groups');
$this->setTab('resources');

$groupHandler = new Group(HOMEPAGE_SERVICE);

// Fetch the logged in user's write access groups to exclude from the lists.
$userWriteGroups = $groupHandler->involvedInCategoryList($_SESSION['user']->getId(), false, WRITE_ACCESS);
$userWriteGroupIds = [];
foreach ($userWriteGroups as $userGroup) {
    $userWriteGroupIds[] = $userGroup['id'];
}

// output free access groups
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
} else {
    $this->setVar('free_list_block', '');
}


// output moderated groups
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
} else {
    $this->setVar('moderated_list_block', '');
}