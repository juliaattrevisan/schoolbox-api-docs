<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//outputs homepage forum in either a flat or threaded view

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/permission.php");

require_once("forum.php");

class OutPut
{
	function OutPut($cid, $view)
	{
		//set view
		if($view)
			$this->view = $view;
		else
			$this->view = FORUM_default_view;
		
		$this->cid = $cid;
		
		$this->db = new dbManager;
		$this->t_body = new Template(TPL_directory);
		if($this->view=="flat")
			$this->t_body->set_file("tplBODY", "forum/forumFlat.htm");
		else
			$this->t_body->set_file("tplBODY", "forum/forumThread.htm");
			
		$this->t_body->set_var("TITLE", HEADING." - Forum");
		
		$this->t_body->set_block("tplBODY", "forum", "forum_block");		
		
		$this->t_body->set_var("CID", $cid);
		
		$query = "SELECT name FROM category WHERE id = $cid";
		$cat = $this->db->getRow($query);
		$this->t_body->set_var("FORUM_HEADING", $cat['name']);


		include_once("../include/page.php");
	}
	
	function setPosts($perm, $postion=1)
	{
		$this->perm = $perm;
		
		$increments = FORUM_increment;
		
		//get post list
		$forum = new forum;

		if($this->view=='flat')
			$total = $forum->countPosts($this->cid);
		else
			$total = $forum->countTopics($this->cid);

		//work out start postion from $postion
		$start = (floor($postion / $increments))*$increments;

		if($start == 0)
			$this->t_body->set_var("PREV", "");
		else
			$this->t_body->set_var("PREV", "<a href=\"viewForum.php?cid={$this->cid}&pos=".($start-$increments)."&view={$_GET['view']}\"><< Prev</a>");
		if(($start+$increments) >= $total)
		{
			$this->t_body->set_var("SELECTION", ($start+1)."-".$total);
			$this->t_body->set_var("NEXT", "");
		}
		else
		{
			$this->t_body->set_var("SELECTION", ($start+1)."-".($start+$increments));
			$this->t_body->set_var("NEXT", "<a href=\"viewForum.php?cid={$this->cid}&pos=".($start+$increments)."&view={$_GET['view']}\">Next >></a>");
		}
		
		if($this->view == 'flat')
		{
			$postList = $forum->getFlatPosts($this->cid, $start, $increments);	
			if(is_array($postList))
			{
				foreach($postList as $post)
				{
					$this->outputPost($post);
				}
			}
			else
				$this->t_body->set_var('forum_block', '');
		}
		else
		{
			//retrieve threaded post tree
			$postTree = $forum->getThreadedPosts($this->cid, $start, $increments);
			
			//get initial list of posts to display
			$initList = '';
			if(is_array($postTree[0]['children']))
			{
				foreach($postTree[0]['children'] as $post)
					$initList .= "'{$post['id']}',";
				$initList = substr($initList, 0, -1);

				$this->t_body->set_var("INIT_POST_LIST", $initList);
				
				//output navigation tree
				$this->t_body->set_var("TOPIC_TREE", $this->outputNavTree($postTree, 1));
				//output posts
				$this->outputPostTree($postTree[0]['children']);
						
			}
			else
			{
				$this->t_body->set_var('INIT_POST_LIST', '');
				$this->t_body->set_var('TOPIC_TREE', '');
				$this->t_body->set_var('forum_block', '');
			}
		}
	}

	function outputNavTree($postTree, $level)
	{
		if($level == 1)
			$text = "\n<ul id=\"forumNav\">\n";
		else
			$text = "\n<ul>\n";
	
		foreach($postTree as $post)
		{
			//this code tells the javascript the ids of children of this post (this avoids javascript traversal)
			$childString = '';
			if(is_array($post['children']))
			{
				foreach($post['children'] as $child)
						$childString .= ",'{$child['id']}'";
			}
			
			if($post['id'])
				$text .= "<li><a href=\"#\" onclick=\"showPosts('{$post['id']}'{$childString})\">{$post['heading']}</a>";
			else
			{
				//this if for when we are producing the top level link (ie id 0)
				$childString = substr($childString, 1);
				$text .= "<li><a href=\"#\" onclick=\"showPosts($childString)\">{$post['heading']}</a>";
			}
			
			if(is_array($post['children']))
				$text .= $this->outputNavTree($post['children'], ($level+1));
			
			$text .= "</li>\n";
		}
		$text .= "</ul>\n";
		return $text;
	}

	function outputPostTree($postTree)
	{
		foreach($postTree as $post)
		{
			$this->outputPost($post);
			if(is_array($post['children']))
				$this->outputPostTree($post['children']);
		}
	}

	function outputPost($post)
	{
		$this->t_body->set_var("ID", $post['id']);
		
		// set owner
		$fullname = formatName($post);
		$owner = "<a href=\"/search/userLookup.php?id={$post['uid']}\">$fullname</a>";
			
		$this->t_body->set_var("OWNER", $owner);
		$this->t_body->set_var("DATE", $post['fulldate']);	
		
		$this->t_body->set_var("HEADING", scrubTEXT($post['heading']));
		$this->t_body->set_var("BODY", nl2br(scrubTEXT($post['message'])));
		
		if($this->perm > 1 || $_SESSION['id'] == $post['owner'])
			$this->t_body->set_var("MODIFY", "<a href=\"/forum/modpost.php?id={$post['id']}\">modify</a> // ");
		else
			$this->t_body->set_var("MODIFY", "");
		
		$this->t_body->parse("forum_block", "forum", true);
	}


	function showPage($msgid)
	{
		if($this->view == 'thread')
		{
			/*
			if($msgid)
				$this->t_body->set_var("OPEN", "objLocalTree.displayBody('$msgid');");
			else
				$this->t_body->set_var("OPEN", "objLocalTree.displayBody('root');");
			*/
		}
		
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function checkTemplate()
	{
		$query = "SELECT position FROM homepage_comp_location WHERE cid = {$this->cid} AND component IN (5)";
		if($this->db->getRow($query))
			return 1;
		else
			return 0;
	}

}

//check perms 
if(is_numeric($_GET['cid']))
{
	$perm = perm($_GET['cid'], 1);
	if($perm > 0)
	{
		if(isParent($_SESSION['gid']))
		{
			//check parent is member of group
			if(!isMember($_GET['cid'], $_SESSION['id']))
				error(14);
		}
		
		$myPage = new OutPut($_GET['cid'], $_GET['view']);
		//only display forum if it exists as a module for this homepage
		if ($myPage->checkTemplate())
		{
			$myPage->setPosts($perm, $_GET['pos']);
			$myPage->showPage($_GET['msgid']);
		}
		else
			error(14);	
	}
	else 
		error(14);
}
else
{
	error(2);
}
?>
