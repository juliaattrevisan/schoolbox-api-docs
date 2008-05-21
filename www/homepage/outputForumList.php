<?php

		$continue = true;
		//dont show forums to parents if they not a direct member of group
		if(isParent($_SESSION['gid']))
		{
			if(!isMember($categoryID, $_SESSION['id']))
			{
				$this->setVar($name, "");
				$continue = false;
			}
		}
	if ($continue) {
		//forum recent posts
		$this->setBlock($componentHandle, "forum", "forum_block");
	
		$query = "SELECT DISTINCT(thread) FROM forum WHERE cid='".$categoryID."' AND deleted = '0' ORDER BY date DESC";
		
		//if no size make unlimited query else make it limited by size
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}";  

		$res = $this->db->query($query);

		if($this->db->countRows() < 1){$this->setVar("forum_block", "");}
		else
		{
			while($curr = $this->db->getNextArray($res))
			{
				$query = "SELECT id, heading FROM forum WHERE parent = '0' AND deleted ='0' AND thread = '".$curr['thread']."'";
				$this->db->query($query);
				$qa = $this->db->getNextArray();

					$url = "/forum/viewForum.php?msgid=".$qa['id']."&cid=".$categoryID;
					
					//set name
					if($queryArray['id'] == "")
						$this->setVar("FORUM_HEAD", scrubTEXT($qa['heading']));
					else
						$this->setVar("FORUM_HEAD", "<a href=\"$url\">".scrubTEXT($qa['heading'])."</a>");
			
					$this->parse("forum_block", "forum", true);
			

				
			}
		}
	}
?>