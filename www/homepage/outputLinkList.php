<?php
		//link output
		$this->setBlock($componentHandle, "link", "link_block");
		
		if(CATEGORY_perms > 1)
			$showHidden = "";
		else
			$showHidden = "AND hidden = 0";
		
		$query =   "SELECT description, url, comment 
					FROM link l 					
					WHERE l.category = " . $categoryID . " 
					$showHidden
					";
		
		$config['sort'] = strtolower($config['sort']);
		$sortFields = array('name' => 'l.description', 'date' => 'submitted');
		if($config['sort'] == 'name' || $config['sort'] == 'date') {
			$sortdir = isset($config['sortdir']) && ($config['sortdir'] == 'Asc' || $config['sortdir'] == 'Desc') ? $config['sortdir'] : 'ASC';
			$query .= " ORDER BY " .$sortFields[$config['sort']]. " " . strtoupper($config['sortdir']);  
		} else
			$query .= " ORDER BY submitted DESC";

		
		//if no size make unlimited query else make it limited by size
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}"; 

		$this->db->query($query);

		if($this->db->countRows() < 1){$this->setVar("link_block", "");}
		else
		{
			while($queryArray = $this->db->getNextArray() )
			{
				//set the url		
				if(isLocalURL($queryArray['url']))
					$this->setVar("LINK_NAME", "<a href=\"".$queryArray['url']."\">".scrubTEXT($queryArray['description'])."</a>");
				else
					$this->setVar("LINK_NAME", "<a href=\"/redir.php?url=".$queryArray['url']."\" target=\"_blank\">".scrubTEXT($queryArray['description'])."</a>");
				
				//set the description
				$this->setVar("LINK_COMMENT", nl2br(scrubTEXT($queryArray['comment'])));
		
				$this->parse("link_block", "link", true);

			}
		}

		//setup block for submission button
		$this->setBlock($componentHandle, "submit_link", "submit_link_block");
		if(CATEGORY_perms < 2)		
			$this->setVar("submit_link_block", "");
		else
			$this->parse("submit_link_block", "submit_link");	



?>