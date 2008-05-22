<?php
		//link output
		$this->setBlock($componentHandle, "link", "link_block");
		
		require_once '../library/link.php';
		$linkHandler = new Link;
		$linkList = $linkHandler->getAllInCategory($categoryID, $config);

		if (is_array($linkList)) {
			foreach ($linkList as $link) {

				//set the url		
				if(isLocalURL($link['url']))
					$this->setVar("LINK_NAME", "<a href=\"".$link['url']."\">".scrubTEXT($link['description'])."</a>");
				else
					$this->setVar("LINK_NAME", "<a href=\"/redir.php?url=".$link['url']."\" target=\"_blank\">".scrubTEXT($link['description'])."</a>");
				
				//set the description
				$this->setVar("LINK_COMMENT", nl2br(scrubTEXT($link['comment'])));
		
				$this->parse("link_block", "link", true);

			}
		} else
			$this->setVar("link_block", "");

		//setup block for submission button
		$this->setBlock($componentHandle, "submit_link", "submit_link_block");
		if(CATEGORY_perms < 2)		
			$this->setVar("submit_link_block", "");
		else
			$this->parse("submit_link_block", "submit_link");	



?>