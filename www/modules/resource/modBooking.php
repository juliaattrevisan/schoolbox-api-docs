<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
************************************************************/

// This page outputs a page that allows modification/adding of a resource booking

require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("../../include/html-time.php");
require_once("conf.php");
require_once("resource.php");
require_once("common.php");
require("booking.php");

if($_POST['date'])
{
	$bookdb = new booking();
	
	//check times are correct
	list($date,$month,$year) = explode('/',$_POST['date'],3); 
	if( !checkdate($month, $date, $year) )		
		error(9);
	
	checkTime($_POST['start_hour'], $_POST['start_minute']);
	checkTime($_POST['end_hour'], $_POST['end_minute']);
	

	if($_POST['start_period'] == 2)
		$startDate = mktime($_POST['start_hour'] + 12, $_POST['start_minute'], 0, $month, $date, $year );
	else
		$startDate = mktime($_POST['start_hour'], $_POST['start_minute'], 0, $month, $date, $year );
	
	if($_POST['end_period'] == 2)
		$endDate = mktime($_POST['end_hour'] + 12, $_POST['end_minute'], 0, $month, $date, $year);
	else
		$endDate = mktime($_POST['end_hour'], $_POST['end_minute'], 0, $month, $date, $year );
	
	if(!$bookdb->checkTimeRange(0, $startDate, $endDate))
		error(103);
	
	
	if($_POST['booking_id'])
	{
		//update an existing booking
		
		$bookingInfo = $bookdb->getBooking($_POST['booking_id']);
		$resdb = new resource();
		
		//make sure modify user is either the owner of resource, creator of booking or su
		if(!($_SESSION['su'] || $bookingInfo['creator'] == $_SESSION['id'] || $resdb->isOwner($_SESSION['id'], $_POST['id'])))
			error(104);

		if($_POST['delete']) //user has requested this booking be deleted.
		{
			$bookdb->deleteBooking($_POST['booking_id']);
		}
		else
		{
			//check resource isnt already booked in that time space (excluding itself of course)
			if($bookdb->isBooked($_POST['resource_id'], $startDate, $endDate, $_POST['booking_id']))
				error(102);
			else//insert booking
				$bookdb->updateBooking($_POST['booking_id'], $startDate, $endDate);
		}
		
		localRedir("/modules/resource/viewResource.php?id={$_POST['resource_id']}");
	}
	else if($_POST['resource_id'])
	{
		//insert new booking
		
		//check resource isnt already booked in that time space
		if($bookdb->isBooked($_POST['resource_id'], $startDate, $endDate))
			error(102);
		else//insert booking
		{
			$resdb = new resource();
			$resInfo = $resdb->getResource($_POST['resource_id']);
			if($resInfo['perm'] == 1) //open
				$bookdb->addBooking($_POST['resource_id'], $_SESSION['id'], $startDate, $endDate);
			else if($resInfo['perm'] == 2) //moderated - request
				$bookdb->requestBooking($_POST['resource_id'], $_SESSION['id'], $startDate, $endDate);
			else
				error(104);
		}
		localRedir("/modules/resource/viewResource.php?id={$_POST['resource_id']}");
	}
}
else if($_GET['authorise'])
{
	$bookdb = new booking();
	$bookingInfo = $bookdb->getBooking($_GET['authorise']);
	$resdb = new resource();
	if($resdb->isOwner($_SESSION['id'], $bookingInfo['rid']))
		$bookdb->authoriseBooking($_GET['authorise']);
	else
		error(104);
		
	localRedir("/modules/resource/viewResource.php?id={$bookingInfo['rid']}");
}
else if($_GET['deny'])
{
	$bookdb = new booking();
	$bookingInfo = $bookdb->getBooking($_GET['deny']);
	$resdb = new resource();
	if($resdb->isOwner($_SESSION['id'], $bookingInfo['rid']))
		$bookdb->denyBooking($_GET['deny']);
	else
		error(104);
	
	localRedir("/modules/resource/viewResource.php?id={$bookingInfo['rid']}");
}
else if($_GET['id'])
{
	if($_GET['booking_id'])
	{
		//retrieve data for both booking and room
		$bookdb = new booking();
		$bookingInfo = $bookdb->getBooking($_GET['booking_id']);
		$resdb = new resource();
		$resourceInfo = $resdb->getResource($_GET['id']);
		
		//make sure modify user is either the owner of resource, creator of booking or su
		if(!($_SESSION['su'] || $bookingInfo['creator'] == $_SESSION['id'] || $resourceInfo['owner'] == $_SESSION['id']))
			error(104);
		
		//modify booking identified by id
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "modules/resource/timeBooking.htm");
		include("../../include/page.php");
		$this->t_body->set_var("TITLE", HEADING." - Resource Booking System - Modify Booking");

		$this->t_body->set_block("tplBODY", "timetable_book", "timetable_book_block");
		$this->t_body->set_block("tplBODY", "time_book", "time_book_block");
		
		$this->t_body->set_var("RESOURCE_NAME", $resourceInfo['name']);


		if($_GET['timetable'])
		{	
			//output timetable booking
			$this->t_body->set_var("WEEKLIST", numDropDown(40, "week", 0));
			$this->t_body->set_var("DAYLIST",	dayDropDown(7, "day", 0));
			$this->t_body->set_var("PERIODLIST", numDropDown(6, "period", 0));
			$this->t_body->set_var("RECURRINGTIMETABLE", "");
			
		
			$this->t_body->set_var("time_book_block", "");
			$this->t_body->parse("timetable_book_block", "timetable_book");
		}
		else
		{
			$startTime = strtotime($bookingInfo['start']);
			$endTime = strtotime($bookingInfo['end']);
			
			$this->t_body->set_var("STARTHOUR", date('g', $startTime));
			$this->t_body->set_var("STARTMIN", date('i', $startTime));
			
			//hack added to fix incorrect conversion of 12 o'clock to pm
			if(date('a', $startTime) == 'am' )
			{
				$this->t_body->set_var("STARTAM", "selected");
				$this->t_body->set_var("STARTPM", "");
			}
			else
			{
				$this->t_body->set_var("STARTAM", "");
				$this->t_body->set_var("STARTPM", "selected");
			}
			
			$this->t_body->set_var("ENDHOUR", date('g', $endTime));
			$this->t_body->set_var("ENDMIN", date('i', $endTime));
			
			if(date('a', $endTime) == 'am')
			{
				$this->t_body->set_var("ENDAM", "selected");
				$this->t_body->set_var("ENDPM", "");
			}
			else
			{
				$this->t_body->set_var("ENDAM", "");
				$this->t_body->set_var("ENDPM", "selected");
			}
			
		
			$this->t_body->set_var("DATE", date('d/m/Y', strtotime($startTime)));
			$this->t_body->set_var("RECURRINGTIME", "");
			
		
			$this->t_body->set_var("timetable_book_block", "");
			$this->t_body->parse("time_book_block", "time_book");
		
		}
		
		$this->t_body->set_var("IDFIELD", "<input type=\"hidden\" name=\"booking_id\" value=\"{$_GET['booking_id']}\"><input type=\"hidden\" name=\"resource_id\" value=\"{$_GET['id']}\">");
		$this->t_body->set_var("DELETE", "<input type=\"submit\" name=\"delete\" value=\"Delete\" class=\"formbutton\">");
		$this->t_body->set_var("ACTION", "Update");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
	else
	{
		//display an empty form to allow users to add new resource
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "modules/resource/timeBooking.htm");
		include("../../include/page.php");
		$this->t_body->set_var("TITLE", HEADING." - Resource Booking System - Create Booking");

		$this->t_body->set_block("tplBODY", "timetable_book", "timetable_book_block");
		$this->t_body->set_block("tplBODY", "time_book", "time_book_block");

		$resdb = new resource();
		$resourceInfo = $resdb->getResource($_GET['id']);
		
		$this->t_body->set_var("RESOURCE_NAME", $resourceInfo['name']);

		if($_GET['timetable'])
		{	
			//output timetable booking
			$this->t_body->set_var("WEEKLIST", numDropDown(40, "week", 0));
			$this->t_body->set_var("DAYLIST",	dayDropDown(7, "day", 0));
			$this->t_body->set_var("PERIODLIST", numDropDown(6, "period", 0));
			$this->t_body->set_var("RECURRINGTIMETABLE", "");
		
			$this->t_body->set_var("time_book_block", "");
			$this->t_body->parse("timetable_book_block", "timetable_book");
		}
		else
		{
			$this->t_body->set_var("STARTHOUR", "");
			$this->t_body->set_var("STARTMIN", "");
			$this->t_body->set_var("STARTAM", "");
			$this->t_body->set_var("STARTPM", "");
		
			$this->t_body->set_var("ENDHOUR", "");
			$this->t_body->set_var("ENDMIN", "");
			$this->t_body->set_var("ENDAM",	"");
			$this->t_body->set_var("ENDPM",	"");
		
			$this->t_body->set_var("DATE",	"");
			$this->t_body->set_var("RECURRINGTIME", "");
				
			$this->t_body->set_var("timetable_book_block", "");
			$this->t_body->parse("time_book_block", "time_book");
		
		}
		
		$this->t_body->set_var("IDFIELD", "<input type=\"hidden\" name=\"resource_id\" value=\"{$_GET['id']}\">");
		$this->t_body->set_var("DELETE", "");
		$this->t_body->set_var("ACTION", "Create");
		$this->t_body->pparse("finaloutput", "tplBODY");

	}
}
else
	error(2);

function dayDropDown($days, $name, $selected)
{

	$dayDropDown = "<select name=\"$name\">";
	for($i = 1; $i <= $days; $i++)
	{
		if($selected == $i)
			$dayDropDown .= "<option value=\"$i\" selected>$i</option>";
		else
			$dayDropDown .= "<option value=\"$i\">$i</option>";
	}
	$dayDropDown .= "</select>";
	return $dayDropDown;
}

function numDropDown($number, $name, $selected)
{

	$numDropDown = "<select name=\"$name\">";
	for($i = 1; $i <= $number; $i++)
	{
		if($selected == $i)
			$numDropDown .= "<option value=\"$i\" selected>$i</option>";
		else
			$numDropDown .= "<option value=\"$i\">$i</option>";
	}
	$numDropDown .= "</select>";
	return $numDropDown;
}




?>