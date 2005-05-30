<?php
/* This class has functions for cleaning up the database at the end of the year */
/* this includes removing deleted files, expired news, logs and submission boxes */

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/adminonly.php");

class clean {
	function clean()
        {
			//always gonna need the db so lets create one..
			$this->db = new dbManager();
        }

	function cleanLoginLog()
	{
		$query = "DELETE FROM login_log";
		$this->db->dbDoQuery($query);
		print "Login records cleaned<br>";
	}

	function cleanLogs()
	{
		//remove all log entries
		$this->cleanLoginLog();
		
		$query = "DELETE FROM homepage_log";
		$this->db->dbDoQuery($query);
		print "Homepage last visit records cleaned<br>";

		$query = "DELETE FROM submission_log";
		$this->db->dbDoQuery($query);
		print "Submission system last visit records cleaned<br>";
	}

	function cleanSubmissionBoxes()
	{
		//unlink all student submissions
		$query = "SELECT id FROM student_submission";
		$this->db->dbDoQuery($query);
	
		while( $sub = $this->db->dbGetNextArray() )
		{
			if(!@unlink(SUBMISSION_directory."/".$sub['id'].".dat"))					
				print "Failed to delete ".SUBMISSION_directory."/".$sub['id'].".dat<br>";					
		}

		$query = "DELETE FROM student_submission";
		$this->db->dbDoQuery($query);
		print "Deleted all student submissions<br>";

		$query = "DELETE FROM submission_box";
		$this->db->dbDoQuery($query);
		print "Deleted all submission boxes<br>";

	}

	function cleanReturnedWork()	
	{
		$query = "SELECT id FROM submission_return";
		$this->db->dbDoQuery($query);
	
		while( $ret = $this->db->dbGetNextArray() )
		{
			if(!@unlink(RETURNS_directory."/".$ret['id'].".dat"))
				print "Failed to delete ".RETURNS_directory."/".$ret['id'].".dat<br>";					
		}

		$query = "DELETE FROM submission_return";
		$this->db->dbDoQuery($query);
		print "Deleted all returned items<br>";

	}

	function cleanNews()
	{
		//deletes all news that is either deleted or has expired
		$query = "DELETE FROM news_items WHERE item_datetime_expires < NOW() || item_deleted = '1'";
		$this->db->dbDoQuery($query);
		print "Deleted ".$this->db->dbCountRowsAffected()." news items<br>";
	}


	function cleanSubNews()
	{
		//deletes all news that is either deleted or has expired
		$query = "DELETE FROM subject_news WHERE datetime_expires < NOW() || deleted = '1'";
		$this->db->dbDoQuery($query);
		print "Deleted ".$this->db->dbCountRowsAffected()." subject news items<br>";
	}
	
 
}

$clean = new clean;

if($_GET['news'] == 1)
	$clean->cleanNews();
if($_GET['subnews'] == 1)
	$clean->cleanSubNews();
if($_GET['loginlog'] == 1)
	$clean->cleanLoginLog();
if($_GET['log'] == 1)
	$clean->cleanLogs();
if($_GET['submission'] == 1)
	$clean->cleanSubmissionBoxes();
if($_GET['return'] == 1)
	$clean->cleanReturnedWork();

?>
