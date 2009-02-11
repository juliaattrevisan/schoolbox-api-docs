<?php

include('include/auth.php');
require_once '../include/sitePage.php';
session_start();	

if( (isset($_POST['username'])) && (isset($_POST['password']) && $_POST['password'] && $_POST['username']) )
{
	$db = new dbManager;

	//first check for brute force attempt
	if( checkBruteForce(&$db) )
	{
		$page = new SitePage(FALSE);
		$page->goLoginPage('You have attempted more than '.LOGINALLOWED.' logins in '.LOGINGRACE.' seconds.<br>
			Please try again in a short while.');
	}		

	session_destroy();

	session_start();
	
	if (authenticate(&$db, $_POST['username'], $_POST['password']) )
	{
		if(isset($_POST['page']) && $_POST['page'])
		{ 
			$page = rawurldecode($_POST['page']);
			localRedir($page);
		}
		else
		{
			localRedir("/home");
		}
	}	
	else
	{
		//get rid of session started earlier to stop it conflicting with next login
		session_destroy();
		$page = new SitePage(FALSE);
		$page->goLoginPage('The username or password you typed is incorrect please try again', $_POST['page']);
		
	}	

}
else
	localRedir('/');




?>
