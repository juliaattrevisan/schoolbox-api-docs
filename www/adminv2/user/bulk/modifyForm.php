<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
SitePage::extendExecutionTime(300);

$userMapper = new \Schoolbox\UserBulkMapper();

$modified = new \Schoolbox\Collection();

if (isset($_POST['updateUsers'])) {
    $userIds = [];
    if (isset($_POST['userId'])) {
        $userIds = array_filter(explode(',', $_POST['userId']));
    }
    if (count($userIds)) {
        $message = "{$_SESSION['user']->getFullname()} ({$_SESSION['user']->getId()}) started a user bulk modify";

        $userMapper->log($message, array());

        /* Modify each user
         */
        foreach ($userIds as $currentUserId) {
            $user = $userMapper->findById($currentUserId);

            $userMapper->bulkModifyUpdate(
                $user,
                $_POST['apply_defaultcampus'],
                $_POST['apply_campus'],
                $_POST['apply_role'],
                $_POST['apply_year'],
                $_POST['apply_eportfolio'],
                $_POST['apply_emailfromusername'],
                $_POST['apply_externalmailclient'],
                $_POST['apply_webmail'],
                $_POST['apply_accountenabled']
            );

            $modified->add($user);
        }
    }
} else {
    if (isset($_POST['deleteUsers'])) {
        $userCollection = $userMapper->findByColumn('id', $userIds);
        $userMapper->hardDelete($userCollection);

        $modified = $userCollection;
    }
}

/* Show some confirmation stuff
 */
$this->setFile(TEMPLATE_content, 'adminv2/user/bulk/modifyResults.html');
$this->setBlock(TEMPLATE_content, 'has_users', 'has_users_block');
$this->setBlock('has_users', 'user', 'user_block');

$this->setVar(
    array(
        'MODIFIED_COUNT' => $modified->count(),
        'has_users_block' => '',
        'user_block' => ''
    )
);

if ($modified->count()) {
    foreach ($modified as $current) {
        $this->setVar(
            array(
                'USER_FIRSTNAME' => $current->getFirstname(),
                'USER_LASTNAME' => $current->getLastname(),
                'USER_EXTERNAL_ID' => $current->getExternalId(),
            )
        );

        $this->parse('user_block', 'user', true);
    }

    $this->parse('has_users_block', 'has_users');
}
