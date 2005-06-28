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
		$cat = $this->db->dbGetSingleRow($query);
		$this->t_body->set_var("FORUM_HEADING", $cat['name']);


		include_once("../include/page.php");
	}
	
	function setPosts($perm, $postion=1)
	{
		
		$increments = 10;

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
			foreach($postList as $post)
			{
				$this->outputPost($post);
			}
		}
		else
		{
			$postTree = $forum->getThreadedPosts($this->cid, $start, $increments);
			$this->t_body->set_var("TOPIC_TREE", $this->outputPostTree($postTree[0]['children'], 1));
		}
	}

	function outputPostTree($postTree, $level)
	{
		if($level == 1)
			$text = "\n<ul id=\"forumNav\">\n";
		else
			$text = "\n<ul>\n";
	
		for($i=0; $postTree[$i]; $i++)
		{
			$text .= '<li>'.$postTree[$i]['heading'];
			if(is_array($postTree[$i]['children']))
				$text .= $this->outputPostTree($postTree[$i]['children'], ($level+1));
			
			$text .= "</li>\n";
		}
		$text .= "</ul>\n";
		return $text;
	}

	function outputPost($post)
	{
		$this->t_body->set_var("ID", $post['id']);
		//TODO: make email enabled
		$this->t_body->set_var("OWNER", $post['firstname']." ".$post['lastname']);
		$this->t_body->set_var("DATE", $post['fulldate']);	
		
		$this->t_body->set_var("HEADING", scrubTEXT($post['heading']));
		$this->t_body->set_var("BODY", nl2br(scrubTEXT($post['message'])));
		
		if($perm > 1 || $_SESSION['id'] == $post['owner'])
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
		if($this->db->dbGetSingleRow($query))
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
