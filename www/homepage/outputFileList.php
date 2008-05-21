<?php
		//file output
		$this->setBlock($componentHandle, "files", "files_block");
		
		if(CATEGORY_perms > 1)
			$showHidden = "";
		else
			$showHidden = "AND hidden = 0";
	
		$query = "SELECT f.id as id, f.description, comment, realname, inline, f.category
				  FROM file f
				  LEFT JOIN allowedFileTypes a ON f.mime_type = a.mime 
				  LEFT JOIN submission_box s on s.fid = f.id
				  WHERE f.category = ".$categoryID."
				  $showHidden
				  AND f.deleted = 0 
				  AND s.id IS NULL ";

		$config['sort'] = strtolower($config['sort']);
		$sortFields = array('name' => 'f.description', 'date' => 'submitted');
		if($config['sort'] == 'name' || $config['sort'] == 'date') {
			$sortdir = isset($config['sortdir']) && ($config['sortdir'] == 'Asc' || $config['sortdir'] == 'Desc') ? $config['sortdir'] : 'ASC';
			$query .= " ORDER BY " .$sortFields[$config['sort']]. " " . strtoupper($config['sortdir']);  
		} else
			$query .= " ORDER BY submitted DESC";
			
		//if no size make unlimited query else make it limited by size
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}";  
	
		$this->db->query($query);

		if($this->db->countRows() < 1){ $this->setVar("files_block", "");}
		else
		{
			while($queryArray = $this->db->getNextArray() )
			{
				//set name
				if($queryArray['realname'] != "")
				{
					if($queryArray['inline'] == 'true')
						$url = "<a href=\"sendframe.php?id=".$queryArray['id']."\" target=\"_blank\">".scrubTEXT($queryArray['description'])."</a>";
					else
						$url = "<a href=\"send.php?id=".$queryArray['id']."\">".scrubTEXT($queryArray['description'])."</a>";
		
					$this->setVar("FILE_NAME", $url);
				}
				else {
					$this->setVar("FILE_NAME", scrubTEXT($queryArray['description']));
				}
				
				$this->setVar("FILE_COMMENT", nl2br(scrubTEXT($queryArray['comment'])));
		
				$this->parse("files_block", "files", true);

			}
		}

		//setup block for submission button
		$this->setBlock($componentHandle, "submit_file", "submit_file_block");
		if(CATEGORY_perms < 2)		
			$this->setVar("submit_file_block", "");
		else
			$this->parse("submit_file_block", "submit_file");	

?>