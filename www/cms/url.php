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
		
		$link = array(	'id'				=> $modid,
								'url' 				=> $this->fixUrl($url),
								'description' => $description,
								'comment'		=> $comment,
								'category'		=> $category,
							);
		$this->db->dbUpdateRow('link', 'id', $link);
		
		$logEntry = array(	'object'			=> 'link',
										'objectId'		=> $modid,
										'action'			=> 'modify',
										'user'			=> $_SESSION['id'],
							);
		
		$this->db->dbInsertRow('cmsLog', $logEntry);
	}

	function addUrl($url, $description, $comment, $category)
	{
		//check allowed perms to add to specified category before proceeding
		$this->checkWriteAccess($category);
		
		$link = array(	'url' 				=> $this->fixUrl($url),
								'description' => $description,
								'comment'		=> $comment,
								'category'		=> $category,
								'owner'			=> $_SESSION['id'],
								'submitted'	=> 'NOW()',
							);
		$this->db->dbInsertRow('link', $link);
		
		$linkId = $this->db->dbInsertId();
		
		$logEntry = array(	'object'			=> 'link',
										'objectId'		=> $linkId,
										'action'			=> 'create',
										'user'			=> $_SESSION['id'],
							);
		
		$this->db->dbInsertRow('cmsLog', $logEntry);
	}

	function deleteUrl($id)
	{
		//check sanity and valid rights

		$linkInfo = $this->getUrl($id);

		//check allowed perms to remove from specified category before proceeding
		$this->checkWriteAccess($linkInfo['category']);

		$this->db->dbDeleteRow('link', 'id', $id);
		
		$logEntry = array(	'object'			=> 'link',
										'objectId'		=> $id,
										'action'			=> 'delete',
										'user'			=> $_SESSION['id'],
							);
		
		$this->db->dbInsertRow('cmsLog', $logEntry);

	}

	function getUrl($id)
	{
		$query = "SELECT url, category, description, comment FROM link WHERE id = '$id'";
		return $this->db->dbGetSingleRow($query);
	}
	
	/* Private functions */
	function fixUrl($url) 
	{
		//clean up and append http if not already exists
		$url = trim($url);
		if( strncasecmp($url,"http://", 7) && strncasecmp($url, "https://", 8) )
			$url = "http://".$url;
	
		return $url;
	}

}
?>
