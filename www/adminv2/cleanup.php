<?php
/* This class has functions for cleaning up the database at the end of the year */
/* this includes removing deleted files, expired news, logs and submission boxes */

if(!isset($this)) {
    die('fail');
}

$group = new Group();
if ( !$group->isSuperuser() ) {
    error(19);
}

class clean {
	function clean()
        {
			//always gonna need the db so lets create one..
			$this->db = DeprecatedDatabase::getInstance();
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

        $query = "DELETE FROM homepage_visits";
        $this->db->query($query);
        print "Homepage visit records cleaned<br>";

        $query = "DELETE FROM submission_log";
		$this->db->query($query);
		print "Submission system last visit records cleaned<br>";
	}

    function cleanCalendar() {
        $query = "DELETE FROM event WHERE access = xce3";
        $this->db->query($query);
    }

    /**
     * @deprecated 2012-08-17
     */
    function cleanSubmissionBoxes()
	{
        error(9, 'This function has been deprecated. Submission boxes are now archived, not deleted.');

//		//unlink all student submissions
//		$query = "SELECT id FROM student_submission";
//		$this->db->query($query);
//
//		while( $sub = $this->db->getNextArray() )
//		{
//			if(!@unlink(Cfg::get('submission_dir')."/".$sub['id'].".dat"))
//				print "Failed to delete ".Cfg::get('submission_dir')."/".$sub['id'].".dat<br>";
//		}
//
//		$query = "DELETE FROM student_submission";
//		$this->db->query($query);
//		print "Deleted all student submissions<br>";
//
//		$query = "DELETE FROM submission_box";
//		$this->db->query($query);
//		print "Deleted all submission boxes<br>";

	}

    /**
     * @deprecated
     */
    function cleanReturnedWork()
	{
        error(9, 'This function has been deprecated. Submission boxes are now archived, not deleted.');

//		$query = "SELECT id FROM submission_return";
//		$this->db->query($query);
//
//		while( $ret = $this->db->getNextArray() )
//		{
//			if(!@unlink(Cfg::get('returns_dir')."/".$ret['id'].".dat"))
//				print "Failed to delete ".Cfg::get('returns_dir')."/".$ret['id'].".dat<br>";
//		}
//
//		$query = "DELETE FROM submission_return";
//		$this->db->query($query);
//		print "Deleted all returned items<br>";

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
			if(!@unlink(Cfg::get('file_dir')."/".$file['id'].".dat"))
			print "Failed to delete ".Cfg::get('file_dir')."/".$file['id'].".dat<br>";
		}

		$query = "DELETE FROM file WHERE deleted = '1'";
		$this->db->query($query);
		print "Deleted ".$this->db->affectedRows()." files<br>";
	}

	function cleanFileSystem() {
		//delete all files from the filesystem that do no have a corresponding record in the database

		//normal files
		foreach(glob(Cfg::get('file_dir').'/*.dat') as $file) {
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
		foreach(glob(Cfg::get('thumbnail_dir').'/*.jpg') as $file) {
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
		foreach(glob(Cfg::get('submission_dir').'/*.dat') as $file) {
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
		foreach(glob(Cfg::get('returns_dir').'/*.dat') as $file) {
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
		$groupHandler = new Group();

		$query = "UPDATE user SET year = year + 1 WHERE group_id IN(".implode(',', $groupHandler->getStudentGroupTypeIds()).") AND year IS NOT NULL";
		$this->db->query($query);
		print "Updated ".$this->db->affectedRows()." student year levels<br>";
	}

	function cleanThumbnails() {

		foreach(glob(Cfg::get('thumbnail_dir').'/*.jpg') as $file) {
					print "Deleting $file<br>";
					if(!@unlink($file))
						print "Failed to delete $file<br>";
		}
	}
	
	function cleanZip() {

		foreach(glob(Cfg::get('zip_dir').'/*') as $file) {
					print "Deleting $file<br>";
					if(!@unlink($file))
						print "Failed to delete $file<br>";
		}
	}

}

if(count($this->arg)) {
    $clean = new clean;
    foreach($this->arg as $arg) {
        switch($arg) {
            case 'news':     $clean->cleanNews(); break;
            case 'calendar': $clean->cleanCalendar(); break;
            case 'subnews':  $clean->cleanSubNews(); break;
            case 'file':     $clean->cleanDeletedFiles(); break;
            case 'loginlog': $clean->cleanLoginLog(); break;
            case 'log':      $clean->cleanLogs(); break;
            case 'rolluser': $clean->rollStudents(); break;
            case 'fileclean': $clean->cleanFileSystem(); break;
            case 'thumbnail': $clean->cleanThumbnails(); break;
            case 'zip':      $clean->cleanZip(); break;
            default: $this->localRedir('/adminv2/cleanup');
        }
    }
    echo '<a href="/adminv2/cleanup">Return to admin</a>';
    die();
}
else {
    $this->setTitle('Admin - Cleanup');
    $this->setFile(TEMPLATE_content, 'adminv2/cleanup.html');

    // include the admin navigation
    $nav = new AdminV2_Nav();
    $nav->parse($this, 'cleanup');

}

?>
