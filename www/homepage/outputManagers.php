<?php

		//output managers
		$this->setBlock($componentHandle, "staff_members", "staff_member_block");
        $this->setBlock($componentHandle, "member_email", "member_email_block");

		require_once SITE_root.'/library/user.php';
		$userHandler = new User;
		$managerList = $userHandler->getManagers($categoryID);

		if (is_array($managerList)) {
			foreach ($managerList as $manager) {

				$name = $userHandler->formatName($manager);

				$emailObj = new Email();
				$email = $emailObj->formatEmailFromArray($manager);

				$this->setVar("MEMBER_NAME", "<a href=\"/search/user/{$manager['uid']}\">$name</a>");

				if($manager['type'] == 3)
					$name .= " (Group Admin.)";

				if($email)
					$emailall .= $email.",";

				$this->setVar('ON_OFF', ++$j%2? '' : 'dark');
				$this->parse("staff_member_block", "staff_members", true);

			}

            $this->setVar("REPLYTO", $emailObj->formatEmailFromArray($_SESSION));
			$this->setVar("EMAILALL", $emailall);
            $this->parse("member_email_block", "member_email");
		} else {
			$this->setVar("staff_member_block", "");
            $this->setVar('member_email_block','');
        }
?>
