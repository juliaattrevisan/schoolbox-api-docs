<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//absentee system, interfaces with synergetic database to

require_once("include/session.php");
require_once("include/include.php");
require_once("include/permission.php");

//check if they are a staff member, students cannot access this
if(isStudent($_SESSION['gid']))
{
      error(19);
}

//student attendances submitted
if ($_SESSION['campus'][0] == 3)
{
	//grimwade uses absentees system in synergy
	localRedir("/absentees/absentees.php");
}
else 
{
	//senior school uses attendances system in synergy
	if ($_GET['code'] && isset($_GET['code'])){
		$code = $_GET['code'];
		localRedir("/absentees/attendances.php?code=$code");
	}
	else{
		localRedir("/absentees/attendances.php");
	}
}
