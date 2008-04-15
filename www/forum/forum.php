<?php

class forum 
{

	function forum()
	{
		$this->db = new dbManager;
	}

	function getThreadID($id)
	{
		//given any id this function will return the thread id

		if($id == 0)
			return 0;
		else
		{
			$query = "SELECT thread FROM forum WHERE id = '$id'";
			$result = $this->db->getRow($query);
			return $result['thread'];
		}
	}

	function addPost($cid, $owner, $parent, $heading, $body)
	{
		//insert post into database 
		
		//check to ensure parent exists
		if($parent != 0)
		{
			if(!$this->getOwner($parent))
				error(9, "Parent thread has been deleted");
		}
		
		$threadID = $this->getThreadID($parent);		

		$query = "INSERT INTO forum (owner, date, heading, message, parent, cid, thread) 
			VALUES ('$owner',  NOW(), '$heading', '$body', '$parent', '$cid', '$threadID')";

		$this->db->query($query);

		if($threadID == 0)
		{
			$threadID = $this->db->dbInsertId();
			$query = "UPDATE forum SET thread='$threadID' WHERE id = '$threadID'";
			$this->db->query($query);
		}	

	}

	function modPost($id, $heading, $body)
	{
		$query = "UPDATE forum SET heading='$heading', message='$body' WHERE id = '$id'"; 
		
		$this->db->query($query);
	}

	function delPost($id)
	{
		$query = "UPDATE forum SET deleted='1' WHERE id = '$id'";
		$this->db->query($query);

		//foreach child delete them to
		$query = "SELECT id FROM forum WHERE parent = '$id'";
		$result = $this->db->query($query);
		
		while($child = $this->db->getNextArray($result))
		{
			$this->delPost($child['id']);
		}	
	}
	
	function getFlatPosts($cid, $start=0, $amount=10)
	{
		$query = "SELECT forum.id, owner, DATE_FORMAT(date, '%W %M %e @ %l:%i%p') AS fulldate, 
		heading, message, parent, 
		user.id as uid, user.title, user.firstname, user.lastname 
		FROM forum LEFT JOIN user ON user.id = owner 
		WHERE cid = '$cid' AND deleted = 0 
		ORDER BY date LIMIT $start, $amount";
		
		return $this->db->getAll($query);		
	}

	function getThreadedPosts($cid, $start=0, $amount=10)
	{
		//first get the list of threads that will be included for view
		$threadQuery = "SELECT id FROM forum WHERE cid = '$cid' AND deleted = 0 AND parent = 0
				ORDER BY date DESC LIMIT $start, $amount";

		$res = $this->db->query($threadQuery);
		if($this->db->countRows($res) > 0)
		{
			//initialise root of tree
			$tree[0]['id'] = 0;	
			$tree[0]['heading'] = 'Show Topics';

			while($thread = $this->db->getNextArray($res))
			{
				//now foreach thread output all the messages in that thread

				$query = "SELECT forum.id, owner, DATE_FORMAT(date, '%W %M %e @ %l:%i%p') AS fulldate, 
				heading, message, parent, 
				user.id as uid,  user.title, user.firstname, user.lastname 
				FROM forum LEFT JOIN user ON user.id = owner 
				WHERE thread = '".$thread['id']."' 
				AND deleted = 0 ORDER BY parent, date";

				$this->db->query($query);
				while($post = $this->db->getNextArray())
				{
					$tree = $this->insertChildToTree($tree, $post);
				}
			}
		}

		return $tree;
	}

	function insertChildToTree($list, $element)
	{
		for($i=0; $list[$i]; $i++)
		{
			//if item is parent insert item
			if($list[$i]['id'] == $element['parent'])
			{
				$list[$i]['children'][] = $element;
				return $list;
			}
			
			if(is_array($list[$i]['children']))
			{
				$sublist = insertChild($list[$i]['children'], $element);
				//if item was inserted then we can leave else we need to keep searching
				//optimisation step (hopefully should stop linear search)
				if($sublist)
				{
					$list[$i]['children'] = $sublist;
					return $list;
				}
			}
		}
		return $list;
	
	}

	function countTopics($cid)
	{
		$query = "SELECT COUNT(id) AS count FROM forum WHERE cid = '$cid' AND deleted = 0 AND parent = 0";
		$result = $this->db->getRow($query);
		return $result['count'];
	}	

	function countPosts($cid)
	{
		$query = "SELECT COUNT(id) AS count FROM forum WHERE cid = '$cid' AND deleted = 0";
		$result = $this->db->getRow($query);
		return $result['count'];
	}
	
	function getCompleteSingle($id)
	{
		$query = "SELECT forum.id, owner, DATE_FORMAT(date, '%W %M %e @ %l:%i%p') AS date, heading, message, parent, 
		cid, user.id as uid, user.username, user.title, user.firstname, user.lastname, user.local_email, user.alt_email 
		FROM forum, user WHERE owner=user.id AND forum.id = '$id' AND deleted = 0";
		return $this->db->getRow($query);
	}

	function getSinglePost($id)
	{
		$query = "SELECT id, owner, heading, message, parent, cid FROM forum WHERE id = '$id' AND deleted = 0";
		return $this->db->getRow($query);     
	}

	function getOwner($id)
	{
		$query = "SELECT owner FROM forum WHERE id = '$id' AND deleted = 0";
		$result = $this->db->getRow($query);
		return $result['owner'];
	}
	
	function checkForumExists($cid)
	{
		$query = "SELECT position FROM homepage_comp_location WHERE cid = $cid AND component IN (".FORUM_COMPONENT.")";
		if($this->db->getRow($query))
			return 1;
		else
			return 0;
	}
	
}		
		
?>
