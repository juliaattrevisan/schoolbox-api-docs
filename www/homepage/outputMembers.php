<?php
		//output users of group
		$this->setBlock($componentHandle, "members", "members_block");
		
		require_once '../library/user.php';
		$userHandler = new User;
		$userList = $userHandler->getAllInCategory($categoryID);
		
		if (is_array($userList)) {
			foreach ($userList as $user) {
		
				$name = formatName($user);
				$email = formatEmail($user);
				
				$this->setVar("MEMBER_NAME", "<a href=\"/search/userLookup.php?id={$user['uid']}\">$name</a>");
					
				if($user['type'] == 3)
					$name .= " (Group Admin.)";
						
				if($email)
					$emailall .= $email.",";
				
				$this->parse("members_block", "members", true);	
			}
			$this->setVar("REPLYTO", formatEmail($_SESSION));
			$this->setVar("EMAILALL", $emailall);
		}
		else
			$this->setVar("members_block", "");
?>