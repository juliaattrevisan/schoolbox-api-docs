<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php');

$page = new SitePage();

if (is_array($_POST['userId']) && count($_POST['userId'])) {
    $userMapper = new \Schoolbox\UserBulkMapper();

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
    }
}

$page->localRedir('/adminv2/user/bulk/modify');