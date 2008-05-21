<?php
		//output managers
		$this->setBlock($componentHandle, "staff_members", "staff_member_block");

		$query = "SELECT title, id as uid, firstname, lastname, type, username, local_email, alt_email FROM user, userAccess
		WHERE id = uid AND service = '" . HOMEPAGESERVICE . "' AND sid = '$categoryID' and type in(3,2) ORDER BY lastname, firstname";
		$this->db->query($query);
	
		if($this->db->countRows() > 0)
		{
			while($manager = $this->db->getNextArray())
			{
				$name = formatName($manager);
				$email = formatEmail($manager);
				
				$this->setVar("MEMBER_NAME", "<a href=\"/search/userLookup.php?id={$manager['uid']}\">$name</a>");
					
				if($manager['type'] == 3)
					$name .= " (Group Admin.)";
						
				if($email)
					$emailall .= $email.",";
				
				$this->parse("staff_member_block", "staff_members", true);

			}
			$this->setVar("EMAILALL", $emailall);
		}
		else
		{
			$this->setVar("staff_member_block", "");
		}

?>