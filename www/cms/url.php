<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

/* Class defines interface between url database and software.         *
 * The following actions can be performed; delete, add, update, get */

class url
{
	function url()
        {
                //always gonna need the db so lets create one..
                $this->db = new dbManager();
        }

        function checkWriteAccess($category)
        {
                require_once("../include/permission.php");

                //dont allow write to root fullstop!!
                if($category == 0)
                        error(2);

                if(perm($category, HOMEPAGESERVICE) < 2)
                        error(6);
        }

	function modifyUrl($url, $description, $comment, $category, $modid)
	{
		//check allowed perms to add to specified category before proceeding
		$this->checkWriteAccess($category);
		
		//cleanup
		$url = trim($url);
		//append http if not already there
		if( strncasecmp($url,"http://", 7) && strncasecmp($url, "https://", 8) )
			$url = "http://".$url;
		
		$query = "UPDATE link 
			SET url='$url', description='$description', comment='$comment', category='$category' 
			WHERE id = '$modid'";
		$this->db->dbDoQuery($query);
		
		$query = "INSERT INTO cmsLog (object, objectId, action, user) VALUES ('link', $modid, 'modify', {$_SESSION['id']})";
		$this->db->dbDoQuery($query);
	}

	function addUrl($url, $description, $comment, $category)
	{
		//check allowed perms to add to specified category before proceeding
		$this->checkWriteAccess($category);
		
		//clean up and append http if not already exists
		$url = trim($url);
		if( strncasecmp($url,"http://", 7) && strncasecmp($url, "https://", 8) )
			$url = "http://".$url;
		
		//INSERT url into table
		$query = "INSERT INTO link 
		(category, owner, url, submitted, description, comment) 
		VALUES 
		('$category','".$_SESSION['id']."','$url',NOW(),'$description','$comment')";
		
		$this->db->dbDoQuery($query);
		$linkId = $this->db->dbInsertId();
		
		$query = "INSERT INTO cmsLog (object, objectId, action, user) VALUES ('link', $linkId, 'create', {$_SESSION['id']})";
		$this->db->dbDoQuery($query);
	}

	function deleteUrl($id)
	{
		//check sanity and valid rights

		$linkInfo = $this->getUrl($id);

		//check allowed perms to remove from specified category before proceeding
		$this->checkWriteAccess($linkInfo['category']);

		//ok all checks completed
		//now delete url from the database

		$query = "DELETE FROM link WHERE id = '".$id."'";
		$this->db->dbDoQuery($query);
		
		$query = "INSERT INTO cmsLog (object, objectId, action, user) VALUES ('link', $id, 'delete', {$_SESSION['id']})";
		$this->db->dbDoQuery($query);

	}

	function getUrl($id)
	{
		$query = "SELECT url, category, description, comment FROM link WHERE id = '$id'";
		return $this->db->dbGetSingleRow($query);
	}

}
?>
