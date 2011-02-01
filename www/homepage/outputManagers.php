<?php

		//output managers
		$this->setBlock($componentHandle, "staff_members", "staff_member_block");

		require_once SITE_root.'/library/user.php';
		$userHandler = new User;
		$managerList = $userHandler->getManagers($categoryID);

		if (is_array($managerList)) {
			foreach ($managerList as $manager) {
			
				$name = formatName($manager);
				$email = formatEmail($manager);
				
				$this->setVar("MEMBER_NAME", "<a href=\"/search/user/{$manager['uid']}\">$name</a>");
					
				if($manager['type'] == 3)
					$name .= " (Group Admin.)";
						
				if($email)
					$emailall .= $email.",";
				
				$this->setVar('ON_OFF', ++$j%2? '' : 'dark');
				$this->parse("staff_member_block", "staff_members", true);

			}
			$this->setVar("EMAILALL", $emailall);
		} else
			$this->setVar("staff_member_block", "");

?>