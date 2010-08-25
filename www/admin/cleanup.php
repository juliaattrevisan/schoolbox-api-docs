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
		$this->db->query($query);
		print "Login records cleaned<br>";
	}

	function cleanLogs()
	{
		//remove all log entries
		$this->cleanLoginLog();
		
		$query = "DELETE FROM homepage_log";
		$this->db->query($query);
		print "Homepage last visit records cleaned<br>";

		$query = "DELETE FROM submission_log";
		$this->db->query($query);
		print "Submission system last visit records cleaned<br>";
	}

    function cleanCalendar() {
        $query = "DELETE FROM calendar WHERE level = 3";
        $this->db->query($query);
    }

	function cleanSubmissionBoxes()
	{
		//unlink all student submissions
		$query = "SELECT id FROM student_submission";
		$this->db->query($query);
	
		while( $sub = $this->db->getNextArray() )
		{
			if(!@unlink(SUBMISSION_directory."/".$sub['id'].".dat"))					
				print "Failed to delete ".SUBMISSION_directory."/".$sub['id'].".dat<br>";					
		}

		$query = "DELETE FROM student_submission";
		$this->db->query($query);
		print "Deleted all student submissions<br>";

		$query = "DELETE FROM submission_box";
		$this->db->query($query);
		print "Deleted all submission boxes<br>";

	}

	function cleanReturnedWork()	
	{
		$query = "SELECT id FROM submission_return";
		$this->db->query($query);
	
		while( $ret = $this->db->getNextArray() )
		{
			if(!@unlink(RETURNS_directory."/".$ret['id'].".dat"))
				print "Failed to delete ".RETURNS_directory."/".$ret['id'].".dat<br>";					
		}

		$query = "DELETE FROM submission_return";
		$this->db->query($query);
		print "Deleted all returned items<br>";

	}

	function cleanNews()
	{
		//deletes all news that is either deleted or has expired
		$query = "DELETE news_items, newsGroup, newsCampus FROM news_items, newsCampus, newsGroup 
					WHERE news_items.item_id = newsGroup.id AND news_items.item_id = newsCampus.id 
						AND (item_datetime_expires < NOW() || item_deleted = '1')";
		$this->db->dbDoQuery($query);
		print "Deleted ".$this->db->affectedRows()." news items<br>";
	}


	function cleanSubNews()
	{
		//deletes all news that is either deleted or has expired
		$query = "DELETE FROM subject_news WHERE datetime_expires < NOW() || deleted = '1'";
		$this->db->query($query);
		print "Deleted ".$this->db->affectedRows()." subject news items<br>";
	}
	
	function cleanDeletedFiles() {
		//deletes all files that are deleted
		$query = "SELECT id FROM file WHERE deleted = '1'";
		$this->db->query($query);
		
		while($file = $this->db->getNextArray()) {
			if(!@unlink(FILE_directory."/".$file['id'].".dat"))
			print "Failed to delete ".FILE_directory."/".$file['id'].".dat<br>";		
		}
		
		$query = "DELETE FROM file WHERE deleted = '1'";
		$this->db->query($query);
		print "Deleted ".$this->db->affectedRows()." files<br>";
	}
	
	function cleanFileSystem() {
		//delete all files from the filesystem that do no have a corresponding record in the database
		
		//normal files
		foreach(glob(FILE_directory.'/*.dat') as $file) {
			preg_match('/.*\/([0-9]+).dat/', $file, $matches);
			if($matches[1]) {
				$query = "SELECT id FROM file WHERE id = '{$matches[1]}'";
				if(!$this->db->getRow($query)) {
					print "Deleting $file<br>";
					if(!@unlink($file))
						print "Failed to delete $file<br>";
				
				}
			}
		}

		//thumbnails
		foreach(glob(THUMBNAIL_IMAGE_CACHE.'/*.jpg') as $file) {
			preg_match('/.*\/([0-9]+)-.*\.jpg/', $file, $matches);
			
			if($matches[1]) {
				$query = "SELECT id FROM file WHERE id = '{$matches[1]}'";
				
				if(!$this->db->getRow($query)) {
					print "Deleting $file<br>";
					if(!@unlink($file))
						print "Failed to delete $file<br>";
				
				}
			}
		}
		

		
		//student submissions
		foreach(glob(SUBMISSION_directory.'/*.dat') as $file) {
		print $file;
		continue;
			preg_match('/.*\/([0-9]+).dat/', $file, $matches);
			if($matches[1]) {
				$query = "SELECT id FROM student_submission WHERE id = '{$matches[1]}'";
				if(!$this->db->getRow($query)) {
					print "Deleting $file<br>";
					if(!@unlink($file))
						print "Failed to delete $file<br>";
					
				}
			}
		}
		
		//returns
		foreach(glob(RETURNS_directory.'/*.dat') as $file) {
			preg_match('/.*\/([0-9]+).dat/', $file, $matches);
			if($matches[1]) {
				$query = "SELECT id FROM submission_return WHERE id = '{$matches[1]}'";
				if(!$this->db->getRow($query)) {
					print "Deleting $file<br>";
					if(!@unlink($file))
						print "Failed to delete $file<br>";
					
				}
			}
		}
		
		
		
	}
	
	function rollStudents() {
		$query = "UPDATE user SET year = year + 1 WHERE group_id IN(".implode(',',unserialize(STUDENTGROUPIDS)).") AND year IS NOT NULL";
		$this->db->query($query);
		print "Updated ".$this->db->countRowsAffected()." student year levels<br>";
	}
	
	function cleanThumbnails() {
		
		foreach(glob(THUMBNAIL_IMAGE_CACHE.'/*.jpg') as $file) {
					print "Deleting $file<br>";
					if(!@unlink($file))
						print "Failed to delete $file<br>";
		}
	}
 
}

$clean = new clean;

if($_GET['news'] == 1)
	$clean->cleanNews();
if($_GET['calendar'] == 1)
	$clean->cleanCalendar();
if($_GET['subnews'] == 1)
	$clean->cleanSubNews();
if($_GET['file'] == 1)
	$clean->cleanDeletedFiles();
if($_GET['loginlog'] == 1)
	$clean->cleanLoginLog();
if($_GET['log'] == 1)
	$clean->cleanLogs();
if($_GET['submission'] == 1)
	$clean->cleanSubmissionBoxes();
if($_GET['return'] == 1)
	$clean->cleanReturnedWork();
if($_GET['rolluser'] == 1)
	$clean->rollStudents();
if($_GET['fileclean'] == 1)
	$clean->cleanFileSystem();
if($_GET['thumbnail'] == 1)
	$clean->cleanThumbnails();

?>
