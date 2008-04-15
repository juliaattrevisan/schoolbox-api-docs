<?php

//outputs form to change email password record and processes the information

require_once("../include/session.php");
require_once("../include/include.php");

class OutPut
{
	function OutPut()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "mail/mailInfo.htm");

		$this->t_body->set_var("TITLE", HEADING." - Setup Mail Account");
		include_once("../include/page.php");
	}

	function set_var($varName, $varContents)
	{
		$this->t_body->set_var($varName, $varContents);
	}

	function enterMailDetails($password)
	{
		//enter the users email account info into database
		
		$db = new dbManager();
		$db->dbConnect();

		$query = "UPDATE user SET password = PASSWORD('".$password."'), email_pass = '".$password."' WHERE id = '".$_SESSION["id"]."'";

		$db->query($query);
	}

	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}

$myPage = new OutPut();

//if form has been submitted
if( (isset($_POST['password']) && $_POST['password']) )
{
	//enter details into the database
	$myPage->enterMailDetails($_POST['password']);
	
	//redirect back to mail to get mail
	localRedir("/mail.php");
}	
else
	$myPage->showPage();
?>
