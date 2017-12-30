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

$userIds = [];
if (isset($_POST['userId'])) {
    $userIds = array_filter(explode(',', $_POST['userId']));
}
if (isset($_POST['updateUsers'])) {
    if (count($userIds)) {
        $message = "{$_SESSION['user']->getFullname()} ({$_SESSION['user']->getId()}) started a user bulk modify";

        $userMapper->log($message, array());

        /* Modify each user
         */
        foreach ($userIds as $currentUserId) {
            $user = $userMapper->findById($currentUserId);

            $userMapper->bulkModifyUpdate(
                $user,
                $_POST['apply_campus'],
                $_POST['apply_role'],
                $_POST['apply_year'],
                $_POST['apply_eportfolio'],
                $_POST['apply_emailfromusername'],
                $_POST['apply_externalmailclient'],
                $_POST['apply_webmail'],
                $_POST['apply_accountenabled'],
                $_POST['apply_hidecontacts'],
                $_POST['apply_hidetimetable'],
                $_POST['apply_position_title'],
                $_POST['apply_displaygradegraph']
            );

            $modified->add($user);
        }
    }
} else {
    if (isset($_POST['deleteUsers'])) {
        $userCollection = $userMapper->findByColumn('id', $userIds);
        $userMapper->delete($userCollection);

        $modified = $userCollection;
    }
}

/* Show some confirmation stuff
 */
$this->setFile(TEMPLATE_content, 'adminv2/user/bulk/modifyResults.html');


$this->setVar(
    array(
        'MODIFIED_COUNT' => $modified->count(),
        'OUTPUT_LOG'     => nl2br(Scrub::text($userMapper->getOutputMessage())),
    )
);

