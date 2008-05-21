<?php
		//output members of group
		$this->setBlock($componentHandle, "members", "members_block");
		
		$query = "SELECT title, id as uid, firstname, lastname, type, username, local_email, alt_email FROM user, userAccess
		WHERE id = uid AND service = '" . HOMEPAGESERVICE . "' AND sid = '$categoryID' AND type > 0 ORDER BY lastname, firstname";
		$this->db->query($query);
		
		if($this->db->countRows() > 0)
		{
			while($member = $this->db->getNextArray())
			{			
				$name = formatName($member);
				$email = formatEmail($member);
				
				$this->setVar("MEMBER_NAME", "<a href=\"/search/userLookup.php?id={$member['uid']}\">$name</a>");
					
				if($member['type'] == 3)
					$name .= " (Group Admin.)";
						
				if($email)
					$emailall .= $email.",";
				
				$this->parse("members_block", "members", true);	
			}
			$this->setVar("REPLYTO", formatEmail($_SESSION));
			$this->setVar("EMAILALL", $emailall);
		}
		else
		{
			$this->setVar("members_block", "");
		}		       
?>