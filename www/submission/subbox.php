<?php

//Submission object
//Contains functions for creating and manipulating submission boxes
require_once('../include/time.php');


class subBox
{
	function subBox()
	{
		$this->db = new dbManager;
	}

	function createSubmission($cid, 			
			$open_date, $open_hour, $open_min, $open_period, 
			$due_date, $due_hour, $due_min, $due_period, 
			$late_date, $late_hour, $late_min, $late_period,$fileid, $desc)
	{
		//extract parts of date, expects in format dd/mm/yyyy
		list($odate,$omonth,$oyear) = explode('/',$open_date,3);
		list($date,$month,$year) = explode('/',$due_date,3);
		list($ldate,$lmonth,$lyear) = explode('/',$late_date,3);
		
		//validate date
		if(!checkdate($omonth,$odate,$oyear) )
			error(15);//open date is wrong
			
		if(!checkdate($month,$date,$year) )
			error(15);//due date is wrong

		if(!checkdate($lmonth,$ldate,$lyear) )
			error(15);//late date is wrong

		if(mktime(0,0,0,$month,$date,$year) > mktime(0,0,0,$lmonth,$ldate,$lyear))
			error(15);//late date is greater than due date

		//validate time
		checkTime($open_hour,$open_min);
		checkTime($due_hour,$due_min);
		checkTime($late_hour,$late_min);
		$otime = createTime($open_hour,$open_min,$open_period);
		$time = createTime($due_hour,$due_min,$due_period);
		$ltime = createTime($late_hour,$late_min,$late_period);
		
		$insertODate = $oyear."-".$omonth."-".$odate." ".$otime;
		$insertDate = $year."-".$month."-".$date." ".$time;
		$insertLDate = $lyear."-".$lmonth."-".$ldate." ".$ltime;

		$query = "INSERT INTO submission_box (category, owner, open_date, date, late_date, fid, created, description) 
			VALUES ('$cid', '".$_SESSION['id']."','$insertODate','$insertDate','$insertLDate','$fileid', NOW(), '$desc' )";

		$this->db->dbDoQuery($query);
	}

	function updateSubmissionDate($sid, 
			$open_date, $open_hour, $open_min, $open_period, 
			$due_date, $due_hour, $due_min, $due_period, 
			$late_date, $late_hour, $late_min, $late_period, $desc)
	{
		//given a submission box it updates the date fields
		
		//extract parts of date, expects in format dd/mm/yyyy
		list($odate,$omonth,$oyear) = explode('/',$open_date,3);
		list($date,$month,$year) = explode('/',$due_date,3);
		list($ldate,$lmonth,$lyear) = explode('/',$late_date,3);
		
		//validate date
		if(!checkdate($omonth,$odate,$oyear) )
			error(15);//open date is wrong
			
		if(!checkdate($month,$date,$year) )
			error(15);//due date is wrong

		if(!checkdate($lmonth,$ldate,$lyear) )
			error(15);//late date is wrong

		if(mktime(0,0,0,$month,$date,$year) > mktime(0,0,0,$lmonth,$ldate,$lyear))
			error(15);//late date is greater than due date

		//validate time
		checkTime($open_hour,$open_min);
		checkTime($due_hour,$due_min);
		checkTime($late_hour,$late_min);
		$otime = createTime($open_hour,$open_min,$open_period);
		$time = createTime($due_hour,$due_min,$due_period);
		$ltime = createTime($late_hour,$late_min,$late_period);
		
		$insertODate = $oyear."-".$omonth."-".$odate." ".$otime;
		$insertDate = $year."-".$month."-".$date." ".$time;
		$insertLDate = $lyear."-".$lmonth."-".$ldate." ".$ltime;

		$query = "UPDATE submission_box SET open_date = '$insertODate', date = '$insertDate', late_date = '$insertLDate', description = '$desc' WHERE id = '$sid'"; 
		$this->db->dbDoQuery($query);
        }

	function deleteSubmissionBox($id)
	{
                        
		//delete all files
		$query = "SELECT id FROM student_submission WHERE box_id = '$id'";
		$this->db->dbDoQuery($query);
		while($file = $this->db->dbGetNextArray())
		{
			//delete from directory
			$filename = $file['id'] . ".dat";
 			unlink(SUBMISSION_directory . "/" . $filename);
		}
		//delete student submissions from database
		$query = "DELETE FROM student_submission WHERE box_id = '$id'";
		$this->db->dbDoQuery($query);
                
		//mark box deleted box but still exists for reference purposes
		$query = "UPDATE submission_box SET deleted = '1' WHERE id = '$id'";
		$this->db->dbDoQuery($query);
    } 

	function undeleteSubmissionBox($id)
	{
		$query = "UPDATE submission_box SET deleted = '0' WHERE id = '$id'";
		$this->db->dbDoQuery($query);
	}		

	function getSubBoxDetails($sid)
	{
		$query = "SELECT open_date, date, late_date, description FROM submission_box WHERE id = '$sid'";
		$this->db->dbDoQuery($query);
		$dates = $this->db->dbGetNextArray();
		
		return $dates;
	}

}

?>
