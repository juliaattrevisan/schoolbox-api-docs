<?php

//Submission object
//Contains functions for creating and manipulating submission boxes

class subBox
{

	function createSubmission($cid, $date, $month, $time, $ldate, $lmonth, $ltime, $fileid, $desc)
	{
		$db = new dbManager;
		$db->dbConnect();

		$year = date("Y");

		//validate date
		if(!checkdate($month,$date,$year) )
		{
			//due date is wrong
			error(15);
		}
		if(!checkdate($lmonth,$ldate,$year) )
		{
			//late date is wrong
			error(15);
		}
		if(mktime(0,0,0,$month,$date,$year) > mktime(0,0,0,$lmonth,$ldate,$year))
		{
                	//late date is greater than due date
			error(15);
		}

		$insertDate = $year."-".$month."-".$date." ".$time;
		$insertLDate = $year."-".$lmonth."-".$ldate." ".$ltime;

		$query = "INSERT INTO submission_box (category, owner, date, late_date, fid, created, description) 
			VALUES ('$cid', '".$_SESSION['id']."','$insertDate','$insertLDate','$fileid', NOW(), '$desc' )";

		$db->dbDoQuery($query);
	}

	function updateSubmissionDate($sid, $date, $month, $time, $ldate, $lmonth, $ltime, $desc)
	{
		//given a submission box it updates the date fields
		$year = date("Y");

		//validate date
		if(!checkdate($month,$date,$year) )
			error(15);	//due date is wrong

		if(!checkdate($lmonth,$ldate,$year) )
                        error(15);	//due date is wrong

                if(mktime(0,0,0,$month,$date,$year) > mktime(0,0,0,$lmonth,$ldate,$year))
                        error(15);	//late date is greater than due date

		$insertDate = $year."-".$month."-".$date." ".$time;
		$insertLDate = $year."-".$lmonth."-".$ldate." ".$ltime;

		$db = new dbManager;
		$db->dbConnect();
		$query = "UPDATE submission_box SET date = '$insertDate', late_date = '$insertLDate', description = '$desc' WHERE id = '$sid'"; 
		$db->dbDoQuery($query);
		$db->dbClose();
        }

	function deleteSubmissionBox($id)
	{
		$db = new dbManager;
		$db->dbConnect();
                        
		//delete all files
		$query = "SELECT id FROM student_submission WHERE box_id = '$id'";
		$db->dbDoQuery($query);
		while($file = $db->dbGetNextArray())
		{
			//delete from directory
			$filename = $file['id'] . ".dat";
 			unlink(SUBMISSION_directory . "/" . $filename);
		}
		//delete student submissions from database
		$query = "DELETE FROM student_submission WHERE box_id = '$id'";
		$db->dbDoQuery($query);
                
		//mark box deleted box but still exists for reference purposes
		$query = "UPDATE submission_box SET deleted = '1' WHERE id = '$id'";
		$db->dbDoQuery($query);
        } 

	function undeleteSubmissionBox($id)
	{
		$db = new dbManager;
		$db->dbConnect();

		$query = "UPDATE submission_box SET deleted = '0' WHERE id = '$id'";
		$db->dbDoQuery($query);
	}		

	function getSubBoxDetails($sid)
	{
		$db = new dbManager;
		$db->dbConnect();
		$query = "SELECT date, late_date, description FROM submission_box WHERE id = '$sid'";
		$db->dbDoQuery($query);
		$dates = $db->dbGetNextArray();
		
		return $dates;
	}

}

?>
