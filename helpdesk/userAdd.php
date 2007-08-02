<?php
# File		: userDetails.php
# Written	: Nick Doyle, 1/7/2002
# Description	: helpdesk user details page

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleUserEdit.php');

define( '_TITLE', 'New User');

$page = new HelpDeskPage;
$page->authCheck(3);
$page->setTitle( _TITLE);

session_start();
$moduleUserEdit = new ModuleUserEdit();
$content = $moduleUserEdit->getContent();

$page->setContent( $content);

$page->printPage();

?>
