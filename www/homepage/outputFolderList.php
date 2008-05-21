<?php
		//file output
		$this->setBlock($componentHandle, "folders", "folders_block");
	
		$query = "SELECT id, name, template FROM category 
		WHERE parent = '".$categoryID."' ORDER BY name DESC";

		//if no size make unlimited query else make it limited by size
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}"; 

		$this->db->query($query);

		if($this->db->countRows() < 1){ $this->setVar("folders_block", "");}
		else
		{
			$flag = 0;
			while($queryArray = $this->db->getNextArray() )
			{
				if(perm($queryArray['id'], 1) > 0)
				{
							//set name
							$this->setVar("FOLDER_NAME", "<a href=\"/browse.php?category=".$queryArray['id']."\">".scrubTEXT($queryArray['name'])."</a>");
					
							if($queryArray['template'])
								$this->setVar("HOMEPAGE_LINK", "<a href=\"/homepage.php?category=".$queryArray['id']."\">homepage</a>");
							else
								$this->setVar("HOMEPAGE_LINK", "");
					
							$this->parse("folders_block", "folders", true);
							$flag = 1;
				}
			}
			if(!$flag) { $this->setVar("folders_block", "");}
		}

		//setup block for submission button
		$this->setBlock($componentHandle, "submit_folder", "submit_folder_block");
		if(CATEGORY_perms < 2)		
			$this->setVar("submit_folder_block", "");
		else
			$this->parse("submit_folder_block", "submit_folder");	
	
?>