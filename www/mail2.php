<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
include('include/include.php');
include('include/session.php');

class MailPage
{
	function MailPage($page = "")
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "mail2.htm");			
		$this->t_body->set_var("TITLE", HEADING." - Mail");
		
		require_once('include/page.php');
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}

$page = new MailPage;
?>
