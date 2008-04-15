<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	These functions return information on the groups the	*
*	user is involved with									*
*   $Id: involved.php,v 1.3 2004/04/27 05:16:48 jleckie Exp $						    
*                                                           *
************************************************************/
function involvedInSubject($code)
{
	//returns true on user being involved in subject (case-insensitive)
	
	if(is_array($_SESSION['subjects']))
	{
		$code = strtoupper($code);
		
		foreach($_SESSION['subjects'] as $subject)
		{
			if($code == strtoupper($subject['code']))
				return 1;
		}
	}
	return 0;
}

function involvedInGroup($cid)
{
	//returns true on user being involved in group

	$db = new dbManager;

	$query = "SELECT category.id FROM userAccess, category 
		WHERE userAccess.uid = '".$_SESSION['id']."' AND userAccess.sid = category.id 
		AND category.id = '$cid'";

	$db->query($query);
	if($db->countRows() > 0) 
		return 1;
	else
		return 0;
}

function involvedCategoryList($id, $subjects, $accessType=1)
{
	//returns a list of categories that the user is involved in
	//optional accessType specifies read=1 , write=2, ownership=3 access levels

	$db = new dbManager;

	//for each subject involved in
	$x=0;
	if(is_array($subjects))
	{
		foreach($subjects as $subject)
		{
			//convert code to id and get name
			$query = "SELECT name, id FROM category WHERE code = '".$subject['code']."'";
			$result = $db->getRow($query);

			$catList[] = array (	'name' 	=>	$result['name'],
									'id' 	=>	$result['id'],
									'code' 	=>	$subject['code'],
								);
		}
	}

	//get list of groups involved in
	$query = "SELECT category.name, category.id FROM userAccess, category 
	WHERE userAccess.uid = $id AND userAccess.sid = category.id
	AND userAccess.type >= '$accessType'";

	$db->query($query);
	while($result = $db->getNextArray())
	{
		$catList[] = array (	'name' 	=>	$result['name'],
								'id' 	=>	$result['id'],
							);
	}
	
	return $catList;
}

?>
