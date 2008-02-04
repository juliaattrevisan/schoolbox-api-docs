<?php
/* This class has functions for cleaning up the database at the end of the year */
/* this includes removing deleted files, expired news, logs and submission boxes */

require_once("../include/include.php");

class clean {
	function clean()
        {
			//always gonna need the db so lets create one..
			$this->db = new dbManager();
        }

	function cleanSubmissionBoxes()
	{
		//unlink all student submissions
		$query = "SELECT id FROM student_submission WHERE submitted < '2007-12-1' ";
		$this->db->dbDoQuery($query);
	
		while( $sub = $this->db->dbGetNextArray() )
		{
			if(!@unlink(SUBMISSION_directory."/".$sub['id'].".dat"))					
				print "Failed to delete ".SUBMISSION_directory."/".$sub['id'].".dat<br>";					
		}

		$query = "DELETE FROM student_submission WHERE submitted < '2007-12-1'";
		$this->db->dbDoQuery($query);
		print "Deleted all student submissions pre 2008<br>";

		$query = "DELETE FROM submission_box WHERE submitted < '2007-12-1'";
		$this->db->dbDoQuery($query);
		print "Deleted all submission boxes pre 2008<br>";

	}

	function cleanReturnedWork()	
	{
		$query = "SELECT id FROM submission_return WHERE submitted < '2007-12-1'";
		$this->db->dbDoQuery($query);
	
		while( $ret = $this->db->dbGetNextArray() )
		{
			if(!@unlink(RETURNS_directory."/".$ret['id'].".dat"))
				print "Failed to delete ".RETURNS_directory."/".$ret['id'].".dat<br>";					
		}

		$query = "DELETE FROM submission_return WHERE submitted < '2007-12-1'";
		$this->db->dbDoQuery($query);
		print "Deleted all returned items pre 2008<br>";

	}

 
}

$clean = new clean;

$clean->cleanSubmissionBoxes();
$clean->cleanReturnedWork();

?>
