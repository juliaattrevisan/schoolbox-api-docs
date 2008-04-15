<?php

function permType()
{
	//Function to check permissions on specified user in detention system 
	//Returns 0 on no permission
	//to set permissions - insert into intranet.userAccess values (userid, 5, 0, accesslevel)
	//	  1 for prefects 
	//	  2 for detention staff 
	//	  3 for heads of houses 
	//	  4 for school steward

	//if superuser just return highest permission type
	if($_SESSION['su'] == 1){
		return 4;
	}

	$db = new dbManager();
	$db->dbConnect();

	//individual access
	$userQuery = "  SELECT type 
			FROM userAccess 
			WHERE service = 5 
			AND uid = '".$_SESSION['id']."'
	";
	$db->query($userQuery);      
	$result = $db->getNextArray();      
	return $result['type'];
}

function permSS()
{
	//Function to determine the School Steward
	$db = new dbManager;
	$db->dbConnect();
	
	$query = "SELECT `uid`
		  FROM `userAccess`
		  WHERE `service` = 5
		  AND `sid` = 1
		  AND `type` = 4
	";
	$db->query($query);
	$result = $db->getNextArray();
	return $result['uid'];
}

function permHoH($house)
{
	//Function to determine the Head of House 
	$db = new dbManager;
	$db->dbConnect();
	
	$query = "SELECT uid 
		  FROM userAccess
		  WHERE service = 5
		  AND sid = '".$house."' 
		  AND type = 3
	";
	$db->query($query);
 	$result = $db->getNextArray();
	return $result['uid'];		  
}

function permHouse()
{
	//Function to determine which house detentions to show 
	$db = new dbManager;
	$db->dbConnect();
	
	$query = "SELECT sid
		  FROM userAccess
		  WHERE service = 5
		  AND uid = '".$_SESSION['id']."'
	"; 
	$db->query($query);
	$result = $db->getNextArray();
	return $result['sid'];
}
?>
db->query($query);
	$result = $db->getNextArray();
	return $result['sid'];
}
?>
