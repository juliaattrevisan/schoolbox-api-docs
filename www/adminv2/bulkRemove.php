<?php
SitePage::extendExecutionTime(300);

if (!$_SESSION['user']->isSuperuser()) {
    error(19);
}

$this->setTitle('Admin - Bulk Remove Users');
$this->setFile(TEMPLATE_content, 'adminv2/bulkRemove.html');
$this->setBlock(TEMPLATE_content, 'user', 'user_block');

// include the admin navigation
$nav = new AdminV2_Nav($this->getDi()->get('config'));
$nav->parse($this, 'user');

/* Two types of upload.. either single user.. or via csv file..
 */
if (isset($_FILES['bulkremove']['tmp_name']) && $_FILES['bulkremove']['tmp_name']) {
    $bulkHandler = new Bulk_User($_FILES['bulkremove']['tmp_name']);
    $allUsers = $bulkHandler->removeUsers();

    if ($allUsers && count($allUsers)) {
        foreach ($allUsers as $currentUser) {
            $this->setVar(array(
                'USER_USERNAME' => $currentUser['username'],
                'USER_STATUS' => $currentUser['status'],
                'USER_ERROR' => $currentUser['error'],
            ));

            $this->parse('user_block', 'user', true);
        }
    } else {
        $this->setVar('user_block', '');
    }
} else {
    error(2);
}
