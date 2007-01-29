<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
include('include/include.php');
include('include/auth.php');

class LoginPage
{
	function LoginPage($page = "")
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplLogin", "login.htm");			
		$this->t_body->set_var("TITLE", HEADING." - Login");
		$this->t_body->set_var("HIDDEN",$page);
	}


	function setOutput($fail)
	{
		//on failure print out message
		$this->setVar("ERROR", $fail);
	
	}

	function setVar($varName, $varContents)
	{
		$this->t_body->set_var($varName, $varContents);
	}
	
	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplLogin");
	}
}

session_start();	

if( (isset($_POST['username'])) && (isset($_POST['password']) && $_POST['password'] && $_POST['username']) )
{
	$db = new dbManager;
	$db->dbConnect();

	//first check for brute force attempt
	if( checkBruteForce(&$db) )
	{
		$myPage = new LoginPage($_POST['page']);
		$myPage->setOutput("You have attempted more than ".LOGINALLOWED." logins in ".LOGINGRACE." seconds.<br>
			Please try again in a short while.");
		$myPage->showPage();
		exit;
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
			localRedir("/home.php");
		}
	}	
	else
	{
		//get rid of session started earlier to stop it conflicting with next login
		session_destroy();
		$page = rawurldecode($_POST['page']);
		$myPage = new LoginPage($page);

		//display bad login message	
		$myPage->setOutput("The username or password you typed is incorrect please try again");
		$myPage->showPage();
		exit;
	}	

}
//if session is setup and the session has either been remembered or not expired let them in
else if(isset($_SESSION['id']) && ($_SESSION['remember'] ||(($_SESSION['lasttime'] + SESSIONLENGTH) > mktime())))
{
	if(isset($_GET['page']) && $_GET['page'])
	{ 
		$page = rawurldecode($_GET['page']);
		localRedir($page);
		
	}
	else
	{
		localRedir("/home.php");
	}
		
} 
else
{
	//just show normal login		
	$myPage = new LoginPage($_REQUEST['page']);
	//if a redir page has been given and they are not logged in inform them what is happening
	if($_REQUEST['page'])
		$myPage->setOutput("You are currently not logged in.  Your session has either expired or you are beginning 
			a new session.  Please log in below to continue browsing the portal. ");
	else
		$myPage->setOutput("To begin a new session please login with your username and password.");
	$myPage->showPage();
	
}

?>
