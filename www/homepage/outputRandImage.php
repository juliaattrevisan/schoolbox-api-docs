<?php

		//outputs an custom image and link to the homepage
		$this->setBlock($componentHandle, "previewimage", "preview_block");
		$this->setVar('IMAGE_TITLE', 'Random Image');

		//get image id
		$query = "SELECT id, comment FROM file WHERE category = {$categoryID} AND deleted = '0' AND hidden = '0' AND INSTR(mime_type, 'image/')
                  ORDER BY RAND() LIMIT 1";
		
		$this->db->query($query);                
		if($image = $this->db->getNextArray())
		{
			//get link
			if($config['size']==0){$config['size']=6;}
				$imageSize=$config['size']*100;

			$imageurl = "/cms/image.php?id={$image['id']}&height={$imageSize}&width={$imageSize}";
			
			$this->setVar("PREVIEW_IMAGE_URL", $imageurl);
			$this->setVar("PREVIEW_IMAGE_DESC", scrubTEXT($image['comment']));

			$this->parse("preview_block", "previewimage");
		}
		else
		{
			$this->setVar("preview_block", "");
		}
		
?>