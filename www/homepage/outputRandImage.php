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
			if($config['pixel']==0)
				$config['pixel']=400;
			if($config['pixel']>600)
				$config['pixel']=600;


			$imageurl = "/cms/image.php?id={$image['id']}&height={$config['pixel']}&width={$config['pixel']}";

			$this->setVar("PREVIEW_IMAGE_URL", $imageurl);
			$this->setVar("PREVIEW_IMAGE_DESC", Scrub::text($image['comment']));

			$this->parse("preview_block", "previewimage");
		}
		else
		{
			$this->setVar("preview_block", "");
		}

?>