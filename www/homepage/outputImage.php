<?php
		
		//outputs an custom image and link to the homepage
		$this->setBlock($componentHandle, "previewimage", "preview_block");
		
		// Make sure there a config option
		if(empty($config['image'])) {
			$config['image'] = 'Preview Image';
			$config['pixel'] = 200;
		}
		$continue = true;
		
		//get image id
		$query = "SELECT id, comment FROM file WHERE category = '".$categoryID."' AND deleted = '0' AND description LIKE '{$config['image']}'";
		$image = $this->db->getRow($query);
		if(!is_array($image)) {
			//try to find another image if we cant find the configured one	
			$query = "SELECT id, comment FROM file WHERE category = {$categoryID} AND deleted = '0' AND INSTR(mime_type, 'image/')";
			$image = $this->db->getRow($query);
			
			if(!is_array($image)) {
				$this->setVar('preview_block', '');
				$continue = false;
			}
		}
		
		if ($continue) {
			

			if($config['pixel']>365){$config['pixel']=365;}
			if($config['pixel']<20){$config['pixel']=20;}
			
			$imageurl = "/cms/image.php?id={$image['id']}&height={$config['pixel']}&width={$config['pixel']}";

				
			$this->setVar("PREVIEW_IMAGE_URL", $imageurl);
			$this->setVar("PREVIEW_IMAGE_DESC", scrubTEXT($image['comment']));
			if (isUrl($config['url'])) {
				$this->setVar("CONTENT_URL_START", "<a href=\"/redir.php?url=".urlencode(urlencode($config['url']))."\" target=\"_blank\">");
				$this->setVar("CONTENT_URL_END", "</a>");
			} else {
				$this->setVar("CONTENT_URL_START", "");
				$this->setVar("CONTENT_URL_END", "");
			}
			$this->parse("preview_block", "previewimage");

		}
?>