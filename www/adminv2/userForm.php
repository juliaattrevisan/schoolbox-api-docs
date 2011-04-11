<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/user.php';

$page = new SitePage;
$userHandler = new User;
$groupHandler = new Group;

/* Superusers only */
if(!$groupHandler->isSuperuser())
    error(19);

if(is_numeric($_POST['userID']) && $_POST['username'] ) {
    
    $user = array(  'id'            => $_POST['userID'],
                    'username'      => $_POST['username'],
                    'title'         => $_POST['title'],
                    'firstname'     => $_POST['firstname'],
                    'lastname'      => $_POST['lastname'],
                    'synergy_id'    => $_POST['database_id'],
                    'local_email'   => $_POST['local_email'],
                    'alt_email'     => $_POST['alt_email'],
                    'group_id'      => $_POST['group'],
                    'password'      => $_POST['password'],
                    'campus'        => $_POST['campus'],
                    'su'            => $_POST['superuser'],
                    'year'          => $_POST['year'],
                    'eportfolio_enabled' => $_POST['eportfolio']
    );

    $userHandler->update($user);
    localRedir('/adminv2/modify-user/'.$user['id']);

}
else if( $_POST['username']) {

    $user = array(  'username'      => $_POST['username'],
                    'title'         => $_POST['title'],
                    'firstname'     => $_POST['firstname'],
                    'lastname'      => $_POST['lastname'],
                    'synergy_id'    => $_POST['database_id'],
                    'local_email'   => $_POST['local_email'],
                    'alt_email'     => $_POST['alt_email'],
                    'group_id'      => $_POST['group'],
                    'password'      => $_POST['password'],
                    'campus'        => $_POST['campus'],
                    'su'            => $_POST['superuser'],
                    'year'          => $_POST['year'],
                    'eportfolio_enabled' => $_POST['eportfolio']
    );

    $userHandler->create($user);
    localRedir('/adminv2/modify-user/'.$user['id']);
}
else if($_POST['delete']) {

}
else if($_POST['login']) {
    require_once SITE_root . '/include/session.php';
    
    $sessionHandler = new Session;

    if($sessionHandler->create($_POST['login']))
            localRedir('/');
    else
        localRedir('/adminv2/home/');

}


?>