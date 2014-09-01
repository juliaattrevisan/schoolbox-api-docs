<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
$userMapper = new \Schoolbox\UserBulkMapper();

if (isset($_POST['updateUsers'])) {
    if (is_array($_POST['userId']) && count($_POST['userId'])) {
        $i = 0;

        foreach($_POST['userId'] as $currentUserId) {
            $user = $userMapper->findById($currentUserId);

            $userMapper->bulkModifyUpdate($user,
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

            $i++;
        }
    }
}
else if (isset($_POST['deleteUsers'])) {
    $userCollection = $userMapper->findByColumn('id', $_POST['userId']);
    $userMapper->hardDelete($userCollection);

    $i = $userCollection->count();
}

/* Show some confirmation stuff
 */
$this->setFile(TEMPLATE_content, 'adminv2/user/bulk/modifyResults.html');

$this->setVar(array(
    'MODIFIED_COUNT' => $i,
));