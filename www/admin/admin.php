<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/adminonly.php");

class displayPage {
function displayPage()
{
	$this->t_body = new Template(TPL_directory);
	$this->t_body->set_file("tplBODY", "admin/admin.htm");
	$this->t_body->set_var("TITLE", HEADING." - Administration");

	include_once("../include/page.php");	

	$this->t_body->pparse("finaloutput", "tplBODY");
}
}

$page = new displayPage;

