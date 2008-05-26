<?php

		$continue = true;
		//dont show forums to parents if they not a direct member of group
		if(isParent($_SESSION['gid']))
		{
			if(!isMember($categoryID, $_SESSION['id']))
			{
				$this->setVar($name, "");
				$continue = false;
			}
		}
		
	if ($continue) {
		//forum recent posts
		$this->setBlock($componentHandle, "forum", "forum_block");
		
		require_once '../library/forum.php';
		$forumHandler = new Forum;
		
		$threadList = $forumHandler->getThreads($categoryID, $config);
		
		if (is_array($threadList)) {
			foreach ($threadList as $thread) {

				$url = "/forum/viewForum.php?msgid=".$thread['id']."&cid=".$categoryID;
				
				//set name
				if($thread['id'] == "")
					$this->setVar("FORUM_HEAD", scrubTEXT($thread['heading']));
				else
					$this->setVar("FORUM_HEAD", "<a href=\"$url\">".scrubTEXT($thread['heading'])."</a>");
		
				$this->parse("forum_block", "forum", true);
				
			}
		} else
			$this->setVar("forum_block", "");
	}
?>