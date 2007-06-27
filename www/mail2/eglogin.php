<?php

session_start();

print_r($_SESSION);

$_SESSION['firstname'] = 'Alex';
$_SESSION['lastname'] = 'Tworowsky';
$_SESSION['id'] = 26;
$_SESSION['sid'] = 1;
$_SESSION['gid'] = 1;
$_SESSION['local_email'] = $user['local_email'];
$_SESSION['lasttime'] = mktime();


$_SESSION['username'] = 'aetworowsky';
$_SESSION['password'] = '8367ybcd42448';

?>