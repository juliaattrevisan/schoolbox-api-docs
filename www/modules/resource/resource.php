<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
************************************************************/

/* Class defines interface between resource database and software.         *
 * The following actions can be performed; delete, add, update */

require_once("conf.php");

class resource
{
	function resource()
        {
                //always gonna need the db so lets create one..
                $this->db = new dbManager();
                $this->db->dbConnect();
        }

	function addResource($name, $owner, $image, $perm, $type, $description, $location)
	{
		$query = "INSERT INTO resource (owner, perm, location, name, description, type)
			VALUES ('$owner', '$perm', '$location', '$name', '$description', '$type')";
 
		$this->db->dbDoQuery($query);
		$resourceid = $this->db->dbInsertId();
		
		if($image['tmp_name'])
		{
			if($this->isImage($image))
				move_uploaded_file($image['tmp_name'], IMAGE_LOCATION . '/' . $resourceid . '.dat');
			else
				error(11, $image['type']);
		}
		
		return $resourceid;
	}

	function modResource($id, $name, $image, $owner, $perm, $type, $description, $location)
	{
		if($_SESSION['su'] || $this->isOwner($_SESSION['id'], $id))
		{
			$query = "UPDATE resource SET name = '$name', perm = '$perm', 
				type = '$type', description = '$description', location = '$location'
				WHERE id = '$id'";

			$this->db->dbDoQuery($query);
			
			if($image['tmp_name'])
			{
				if($this->isImage($image))
					move_uploaded_file($image['tmp_name'], IMAGE_LOCATION . '/' . $id . '.dat');
				else
					error(11, $image['type']);
			}
		}
		else
			error(101);
	}

	function delResource($id)
	{
		if($this->isOwner($_SESSION['id'], $id))
		{
			//remove image
			unlink( IMAGE_LOCATION . '/' . $id . '.dat');
		
			//remove from db
			$query = "DELETE FROM resource WHERE id = '$id'";
			return $this->db->dbDoQuery($query);
		}
		else
			error(101);
		
	}


	function getResource($id)
	{
		$query = "SELECT resource.id, user.firstname, 
		user.lastname, perm, location, room,
		resource.name, description, resource.type, 
		luResourceType.name as type_name, 
		luResourceLocation.name as location_name 
		FROM resource, luResourceType, luResourceLocation, user
		LEFT JOIN roomResource ON resource.id = roomResource.resource
		WHERE resource.id = '{$id}' AND resource.type = luResourceType.id
		AND location = luResourceLocation.id AND owner = user.id"; 

		$this->db->dbDoQuery($query);
		return $this->db->dbGetNextArray();
	}

	function getAssignedResources($id)
	{
		$query = "SELECT resource.id, resource.name 
				  FROM resource JOIN roomResource ON roomResource.resource = resource.id
				  WHERE roomResource.room = '{$id}'";
		
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
		{
			while($resources[] = $this->db->dbGetNextArray());
			array_pop($resources);
		}
		return $resources;
	}    

	function getAvailableResources($begin, $end)
	{
		$query = "SELECT resource.id, resource.name,
				luResourceType.name as type_name,
                		luResourceLocation.name as location_name
			  FROM resource, luResourceType, luResourceLocation, resourceBooking
			  WHERE resource.id = '{$id}' AND resource.type = luResourceType.id
                		AND location = luResourceLocation.id
				AND resourceBooking.rid = resource.id
				AND resourceBooking.start = '$begin'
				AND resourceBooking.end = '$end'
				ORDER BY luResourceType.name, resource.name"; 

		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
		while($available[] = $this->db->dbGetNextArray());
		return $available;

	}	

	function getAllResources()
	{
		$query = "SELECT resource.id, resource.name,
				luResourceType.name as type_name,
				luResourceType.type as type_type,
               	luResourceLocation.name as location_name
			  	FROM resource, luResourceType, luResourceLocation
				WHERE resource.type = luResourceType.id AND location = luResourceLocation.id
				ORDER BY luResourceType.name, resource.name";
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
		{
			while($resources[] = $this->db->dbGetNextArray());
			array_pop($resources);
		}
		return $resources;
	}
	
	function isOwner($user, $resource)
	{
		$query = "SELECT id FROM resource WHERE owner = '$user' AND id = '$resource'";
		$this->db->dbDoQuery($query);
		
		if($this->db->dbGetNextArray())
			return true;
		
		return false;
	}
	
	//private functions
	function isImage($image)
	{
		$fileInfo = pathinfo($image['name']);
		$extension = strtolower($fileInfo['extension']);
		
		return allowedImages($image['type'], $extension);
	}
}
?>
