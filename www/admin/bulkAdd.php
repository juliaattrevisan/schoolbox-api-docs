<?php

/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/


/* 
CSV fields

	0. username
	1. title
	2. firstname
	3. surname
	4. synergetic 
	5. group
	6. campus
	7. year
	8. password
	9. webmail
	10 alt. email
*/

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/adminonly.php");
require_once("user.php");

class BulkAdd {

	function parseCSV($file) {
		$userDB = new user;

		if (is_file($file)){
			$handle = fopen($file, "r");
			while (($user = fgetcsv($handle, 1000, ",")) !== FALSE) {
				if($user[0] != "" && $user[2] != "" && $user[3] != "")
				{	
					// get campus id and group
					$user[5] = is_numeric($user[5]) ? $user[5] : $userDB->getGroupID($user[5]);
					if(!$user[5]) {
						print "User {$user[0]} has <b>not</b> been added. (Unknown Group)";
						continue;
					}
					
					$user[6] = is_numeric($user[6]) ? $user[6] : $userDB->getCampusID($user[6]);
					if(!$user[6]) {
						print "User {$user[0]} has <b>not</b> been added. (Unknown Campus)";
						continue;
					}
				
					$result = $userDB->addUser($user[0], $user[1], $user[2], $user[3], $user[4], $user[5], $user[6], $user[7], $user[8], $user[9], $user[10]);
					
					if(!$result) { 
						//adding failed
						//check username and synergy already exists
						$query = "SELECT id FROM user WHERE username = '{$user[0]}' AND synergy_id = '{$user[4]}'";
						$updateUser = $userDB->db->getRow($query);
						if(is_array($updateUser)) {
						
							$userDB->updateUser($updateUser['id'], $user[0], $user[1], $user[2], $user[3], $user[4], $user[9], $user[10], $user[5], $user[8], $user[6], 0, $user[7]);
							print "Updating {$user[0]}<br>";					
						}
					}
					else {
						print "User {$user[0]} has been added<br>";
					}					
				}
			}
		}
	}
}

$page = new BulkAdd;


        
//two types of upload.. either single user.. or via csv file..
if(isset($_FILES['bulkadd']['tmp_name']) && $_FILES['bulkadd']['tmp_name'])
{
        //file submitted
        //parse file
        $page->parseCSV($_FILES['bulkadd']['tmp_name']);
        
        print "<br>Add Completed";
}
else
{   
        //not enough info submitted fail
        //$page->displayPage();
}
?>