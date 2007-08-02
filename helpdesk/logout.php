<?php
# File		: logout.php.php
# Written	: Nick Doyle, 9/7/2002
# Description	: helpdesk logout

require_once( 'include/helpDeskPage.php');
require_once( 'template.php');
require_once( 'common.php');

define( '_TITLE', 'Logout');

session_start();
setcookie(session_name(),'');		# Over-write the client's cookie
session_unset();
session_destroy();

$loginPage = 'Location: http://' . $_SERVER['HTTP_HOST'] . dirname( $_SERVER['PHP_SELF']) . '/login.php?logged_out=1';
header( $loginPage);

?>
