<?php

		//output users of group
		$this->setBlock($componentHandle, "members", "members_block");
        $this->setBlock($componentHandle, "member_email", "member_email_block");
		require_once SITE_root.'/library/user.php';
		$userHandler = new User;
		$userList = $userHandler->getAllInCategory($categoryID);
        $emailObj = new Email();

		if (is_array($userList)) {
			foreach ($userList as $user) {

				$name = $userHandler->formatName($user);
				$email = $emailObj->formatEmailFromArray($user);

				$this->setVar("MEMBER_NAME", "<a href=\"/search/user/{$user['uid']}\">$name</a>");

				if($user['type'] == 3)
					$name .= " (Group Admin.)";

				if($email)
					$emailall .= $email.",";

				$this->setVar('ON_OFF', ++$j%2? '' : 'dark');
				$this->parse("members_block", "members", true);
			}
			$this->setVar("REPLYTO", $emailObj->formatEmailFromArray($_SESSION));
			$this->setVar("EMAILALL", $emailall);
             $this->parse("member_email_block", "member_email");

		}
		else{
			$this->setVar("members_block", '');
            $this->setVar('member_email_block','');
        }
?>