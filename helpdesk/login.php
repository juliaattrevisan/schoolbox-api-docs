<?php
# File		: login.php.php
# Written	: Nick Doyle, 5/7/2002
# Description	: helpdesk login

require_once( 'include/helpDeskPage.php');
require_once( 'template.php');
require_once( 'common.php');

session_start();
//session_unset();
//session_destroy();

$page = new HelpDeskPage;

$tpl = new Template(TPL_DIR);
$tpl->set_file('tplLogin', 'login.html');

	# Initialise session
if( isset( $_POST[ 'login'])) {
	$username = $_POST[ 'username'];
	$passwd = $_POST[ 'passwd'];
	if($page->initLogin($username, $passwd))
	{
		if($_POST['page']){
			redirectRoot($_POST['page']);
		}
		else {
			redirectTo(PAGE_INITIAL);
		}
	}
	else
	{
		redirectTo(PAGE_LOGIN_FAIL);
	}
}

	# Main responsibility of the pages is to control/define the content (may be constructed of modules)


if( $_GET[ 'authfail']) 
	{
	$content = '<font color="red">Password incorrect, please try again.</font><br><br>';
	}
else
	{
	$content = "";
	}

$tpl->set_var('PAGE', $_GET['page']);
$tpl->set_var('EMSG', $content);
$tpl->pparse('Output', 'tplLogin');

?>
