<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//displays news

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/permission.php");
require_once("../include/html-parse.php");

require_once("forum.php");

class OutPut
{
	function OutPut($view)
	{
		//set view
		if($view)
			$this->view = $view;
		else
			$this->view = FORUM_default_view;
		
		$this->db = new dbManager;
		$this->t_body = new Template(TPL_directory);
		if($this->view=="flat")
			$this->t_body->set_file("tplBODY", "forum/forumFlat.htm");
		else
			$this->t_body->set_file("tplBODY", "forum/forumThread.htm");
			
		$this->t_body->set_var("TITLE", HEADING." - Forum");
		
		$this->t_body->set_block("tplBODY", "forum", "forum_block");

		include_once("../include/page.php");
	}
	
	function setPosts($cid, $perm, $postion=1)
	{
		$increments = 10;

		//get post list
		$forum = new forum;

		if($this->view)
			$total = $forum->countPosts($cid);
		else
			$total = $forum->countTopics($cid);

		//work out start postion from $postion
		$start = (floor($postion / $increments))*$increments;

		if($start == 0)
			$this->t_body->set_var("PREV", "");
		else
			$this->t_body->set_var("PREV", "<a href=\"viewForum.php?cid=$cid&pos=".($start-$increments)."&view={$_GET['view']}\"><< Prev</a>");
		if(($start+$increments) >= $total)
		{
			$this->t_body->set_var("SELECTION", ($start+1)."-".$total);
			$this->t_body->set_var("NEXT", "");
		}
		else
		{
			$this->t_body->set_var("SELECTION", ($start+1)."-".($start+$increments));
			$this->t_body->set_var("NEXT", "<a href=\"viewForum.php?cid=$cid&pos=".($start+$increments)."&view={$_GET['view']}\">Next >></a>");
		}
		if($this->view == 'flat')
			$postList = $forum->getFlatPosts($cid, $start, $increments);	
		else
			$postList = $forum->getThreadedPosts($cid, $start, $increments);
		
		$query = "SELECT name FROM category WHERE id = '$cid'";
		$cat = $this->db->dbGetSingleRow($query);

		if($this->view == 'thread')
		{
			//setup root node for javascript tree
			$rootNode = "rootobj";
			$this->t_body->set_var("ROOTOBJ", $rootNode);
		}
	
		$this->t_body->set_var("ROOTNAME", $cat['name']);

		$this->t_body->set_var("CID", $cid);

		if(is_array($postList))
		{
			foreach($postList as $post)
			{
				if($this->view == 'thread')
				{
					//set javascript object names
					$this->t_body->set_var("OBJ", "o".$post['id']);
					if($post['parent'] == 0)
						$this->t_body->set_var("PARENTOBJ", $rootNode.".root");
					else
						$this->t_body->set_var("PARENTOBJ", "o".$post['parent']);
				}
				
				$this->t_body->set_var("ID", $post['id']);
				$this->t_body->set_var("HEADING", html_encode($post['heading']));
				$this->t_body->set_var("BODY", preg_replace("/(\015\012)|(\015)|(\012)/","<br />", html_encode($post['message'])));
				$this->t_body->set_var("OWNER", $post['firstname']." ".$post['lastname']);
				$this->t_body->set_var("DATE", $post['fulldate']);	
				
				if($perm > 1 || $_SESSION['id'] == $post['owner'])
				{
					if($this->view == 'flat')					
						$this->t_body->set_var("MODIFY", "<a href=\"/forum/modpost.php?id={$post['id']}\">modify</a> // ");
					else
						$this->t_body->set_var("MODIFY", "true");
				}
				else
				{
					if($this->view == 'flat')		
						$this->t_body->set_var("MODIFY", "");
					else
						$this->t_body->set_var("MODIFY", "false");
				}
				$this->t_body->parse("forum_block", "forum", true);
			}
		}
		else
			$this->t_body->set_var("forum_block", "");

	}


	function showPage($msgid)
	{
		if($this->view == 'thread')
		{
			if($msgid)
				$this->t_body->set_var("OPEN", "objLocalTree.displayBody('$msgid');");
			else
				$this->t_body->set_var("OPEN", "objLocalTree.displayBody('root');");
		}
		
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function checkTemplate($cid)
	{
		$query = "SELECT position FROM homepage_comp_location WHERE cid = '$cid' AND component IN (5)";
		if($this->db->dbGetSingleRow($query))
			return 1;
		else
			return 0;
	}

}

//check perms 
if(isset($_GET['cid']) && $_GET['cid'])
{
	$perm = perm($_GET['cid'], 1);
	if($perm > 0)
	{
		$myPage = new OutPut($_GET['view']);
		//only display forum if it exists as a module for this homepage
		if ($myPage->checkTemplate($_GET['cid']))
		{
			$myPage->setPosts($_GET['cid'], $perm, $_GET['pos']);
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
