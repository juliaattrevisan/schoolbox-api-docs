<?php

//Submission object
//Contains functions for creating and manipulating submission boxes

class subBox
{
	function subBox()
	{
		$this->db = new dbManager;
	}

	function createSubmission($cid, $due_date, $due_hour, $due_min, $due_period, $late_date, $late_hour, $late_min, $late_period, $fileid, $desc)
	{
		//extract parts of date, expects in format dd/mm/yyyy
		list($date,$month,$year) = explode('/',$due_date,3);
		list($ldate,$lmonth,$year) = explode('/',$late_date,3);
		
		//validate date
		if(!checkdate($month,$date,$year) )
			error(15);//due date is wrong

		if(!checkdate($lmonth,$ldate,$year) )
			error(15);//late date is wrong

		if(mktime(0,0,0,$month,$date,$year) > mktime(0,0,0,$lmonth,$ldate,$year))
			error(15);//late date is greater than due date

		//validate time
		$this->checkTime($due_hour,$due_min);
		$this->checkTime($late_hour,$late_min);
		$time = $this->createTime($due_hour,$due_min,$due_period);
		$ltime = $this->createTime($late_hour,$late_min,$late_period);
		

		$insertDate = $year."-".$month."-".$date." ".$time;
		$insertLDate = $year."-".$lmonth."-".$ldate." ".$ltime;

		$query = "INSERT INTO submission_box (category, owner, date, late_date, fid, created, description) 
			VALUES ('$cid', '".$_SESSION['id']."','$insertDate','$insertLDate','$fileid', NOW(), '$desc' )";

		$this->db->dbDoQuery($query);
	}

	function updateSubmissionDate($sid, $due_date, $due_hour, $due_min, $due_period, $late_date, $late_hour, $late_min, $late_period, $desc)
	{
		//given a submission box it updates the date fields
		
		//extract parts of date, expects in format dd/mm/yyyy
		list($date,$month,$year) = explode('/',$due_date,3);
		list($ldate,$lmonth,$year) = explode('/',$late_date,3);

		//validate date
		if(!checkdate($month,$date,$year) )
			error(15);	//due date is wrong

		if(!checkdate($lmonth,$ldate,$year) )
			error(15);	//due date is wrong

		if(mktime(0,0,0,$month,$date,$year) > mktime(0,0,0,$lmonth,$ldate,$year))
			error(15);	//late date is greater than due date
			
		//validate time
		$this->checkTime($due_hour,$due_min);
		$this->checkTime($late_hour,$late_min);
		$time = $this->createTime($due_hour,$due_min,$due_period);
		$ltime = $this->createTime($late_hour,$late_min,$late_period);

		$insertDate = $year."-".$month."-".$date." ".$time;
		$insertLDate = $year."-".$lmonth."-".$ldate." ".$ltime;

		$query = "UPDATE submission_box SET date = '$insertDate', late_date = '$insertLDate', description = '$desc' WHERE id = '$sid'"; 
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
		$query = "SELECT date, late_date, description FROM submission_box WHERE id = '$sid'";
		$this->db->dbDoQuery($query);
		$dates = $this->db->dbGetNextArray();
		
		return $dates;
	}
	
	function checkTime($hour, $minute)
	{
		if ($hour < 0 || $hour > 12 || $minute < 0 || $minute >= 60 )
			error(9, "Invalid time selected");
	}		

	function createTime($hour, $minute, $period)
	{
		//in this strange world we live in 12:00 am is actually 00:00 not 12:00 and 12:00 pm is actually 12:00 no 24:00 or 0:00  
		if($period == 'am') //am time period
		{
			$time = ($hour%12).":".$minute.":00";
		}
		else if($period == 'pm') //pm period
		{

			$time = (($hour%12)+12).":".$minute.":00";
		}
		else
			error(9, "Invalid time selected");
		
		return $time;
	}

}

?>
