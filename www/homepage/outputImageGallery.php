<?php
		define("IMAGESPERROW", 4);
        
        //output thumbnails
        $this->setBlock($componentHandle, "thumb_row", "row_block");
        $this->setBlock("thumb_row", "thumb_cell", "cell_block");
		
		if ($config['size'] == 0)
			$lim = "";
		else
			$lim = " LIMIT 1, ".IMAGESPERROW * $config['size'];
			
        $query = "SELECT id, file.description FROM file LEFT JOIN allowedFileTypes ON mime_type = mime WHERE category = '{$categoryID}' 
					AND deleted = '0' AND INSTR(mime_type, 'image/') AND inline = 'true'
                	ORDER BY submitted".$lim;
		$this->db->query($query);

        $count = 1;
        $flag = 0;
		$this->setVar("CATID", $categoryID);
		if($this->db->countRows() > 0 )
		{ 
	       	while( $thumbnail = $this->db->getNextArray() )
        	{
				$this->t_body->set_VAR("THUMBNAIL", "<a href=\"/cms/image.php?id={$thumbnail['id']}&height=550&width=600\" rel=\"lightbox[portal]\" title=\"{$thumbnail['description']}\">
                 <img src=\"/cms/image.php?id={$thumbnail['id']}&height=".THUMBNAIL_size."&width=".THUMBNAIL_size."&resample=0\" alt=\"{$thumbnail['description']}\" border=\"0\"></a>");

				if(($count % IMAGESPERROW) == 0)
				{
            		$this->parse("cell_block", "thumb_cell", true);
		    		$this->parse("row_block", "thumb_row", true);                		
		    		$flag = 1;
				}
            	else if($flag)
            	{
					$this->parse("cell_block", "thumb_cell", false);
					$flag = 0;
				}
				else
				{
					$this->parse("cell_block", "thumb_cell", true);
				}
	
				$count++;
			}
		
			$count--;
			
			if($count % IMAGESPERROW != 0)
			{
				for(;$count % IMAGESPERROW != 0; $count++)
				{
					$this->t_body->set_VAR("THUMBNAIL", "");
					$this->parse("cell_block", "thumb_cell", true);
				}
		
				$this->parse("row_block", "thumb_row", true);
			}
		}//end if
		else
		{
			$this->t_body->set_VAR("row_block", "");


?>