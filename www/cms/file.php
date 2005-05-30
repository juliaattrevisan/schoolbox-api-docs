<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

/* Class defines interface between file database and software.         *
 * The following actions can be performed; delete, add, update */

class file
{
	function file()
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

	function modifyFile($description, $comment, $category, $type, $modid)
        {
                //check allowed perms to add to specified category before proceeding
		$this->checkWriteAccess($category);		

                $query = "UPDATE file 
			SET description='$description', comment='$comment', category='$category', type='$type' 
			WHERE id = '$modid'";
                $this->db->dbDoQuery($query);
        }

        function addFile($file_name, $file_type, $description, $comment, $category, $type)
        {
                //check allowed perms to add to specified category before proceeding
		$this->checkWriteAccess($category);		

                //INSERT file details
                $query = "INSERT INTO file 
				(category, owner, realname, submitted, 
				description, comment, type, mime_type)
                	VALUES 
				('".$category."','".$_SESSION['id']."','$file_name', NOW(), 
				'$description', '$comment', '$type','$file_type')";

                $this->db->dbDoQuery($query);

                //get file ID from INSERT
                $fileId = $this->db->dbInsertId();

                if( $_FILES['file']['tmp_name'] )
                {
                        //save file using file ID as name
                        $newFileName = $fileId . ".dat";
                        copy($_FILES['file']['tmp_name'], FILE_directory ."/". $newFileName);
                }

                return $fileId;

        }

	function deleteFile($id)
	{
		//check sanity and valid rights
		//check file exists. and user has nescessary rights
		$fileInfo = $this->getFile($id);
		if($fileInfo['category'])
			$this->checkWriteAccess($fileInfo['category']);	
		else
			error(41);

		//ok all checks completed

		//now delete file by updating it as deleted
		$query = "UPDATE file SET deleted = '1' WHERE id = '".$id."'";
        	$this->db->dbDoQuery($query);

		//we leave file incase we need it but we should clean up any cached files right now
		

	}

	function getFile($id)
	{
                $query = "SELECT category, description, comment, type FROM file WHERE id = '$id'";
                return $this->db->dbGetSingleRow($query);
	} 

}
?>
