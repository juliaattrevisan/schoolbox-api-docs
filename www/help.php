<?php

require_once("include/session.php");
require_once("include/include.php");

class OutPut
{
	function OutPut()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "help.htm");

		$this->t_body->set_var("TITLE", HEADING." - Help");
	}

	function set_var($varName, $varContents)
	{
		$this->t_body->set_var($varName, $varContents);
	}

	function showPage()
	{
		include("include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}

$myPage = new OutPut();

$myPage->showPage();
?>
