<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/adminonly.php");
require_once("user.php");

class BulkRemove{

	function displayPage()
	{
		//nothing has been submitted
		//display input page..
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/bulkrm.htm");
		$this->t_body->set_var("TITLE", HEADING." - Bulk User Remove ");
	
		include_once("../include/page.php");	
	
	}
	function parseCSV($file)
	{
		$userDb = new user;
	
		if (is_file($file))
		{
			$handle = fopen($file, "r");
			while (($user = fgetcsv($handle, 1000, ",")) !== FALSE) {
				
				if($user[0] != "")
				{
					//if transfer info is included use that otherwise just use default
					if($user[1])
						$trans = $user[1];
					else
						$trans = 1;
			
					if(is_numeric($user[0])) //if synergy id delete as id
					{
						if($userDb->deleteSynUser($user[0], $trans))
							print "Removed ". $user[0]."<br>";
						else
							print "Failed ".$user[0]."<br>";
					}
					else //try username
					{
						if($userDb->deleteUsername(mysql_real_escape_string($user[0]), $trans))
							print "Removed ". $user[0]."<br>";
						else
							print "Failed ".$user[0]."<br>";
					}
				}
			}
		}
	}
}

$page = new BulkRemove();


        
//two types of upload.. either single user.. or via csv file..
if(isset($_FILES['bulkremove']['tmp_name']) && $_FILES['bulkremove']['tmp_name'])
{
        //file submitted
        //parse file
        $page->parseCSV($_FILES['bulkremove']['tmp_name']);
        
        print "<br>Remove Completed";
}
else
{   
        //not enough info submitted fail
        $page->displayPage();
}
