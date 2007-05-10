<?php

//This file allows the submission and modification of posts to the forum

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/permission.php");

require_once("forum.php");

class pageTpl
{

function pageTpl()
{
    $this->t_body = new Template(TPL_directory);
    $this->t_body->set_file("tplBODY", "forum/modpost.htm");

    $this->set_var("TITLE", HEADING." - Submit Post");
    
    include_once("../include/page.php");
    
    $this->set_var("FORMSUB_URL", "/forum/modpost.php");
	
	$this->t_body->set_block("tplBODY", "reply_to", "reply_to_block");
	$this->t_body->set_block("tplBODY", "delete_button", "delete_button_block");
	 
}

function putEmpty($cid, $parent) {

	if($parent){
		$forum = new forum;
		$replyto = $forum->getCompleteSingle($parent);
		
		$this->set_var("REPLY_HEADING", $replyto['heading']);
		$this->set_var("REPLY_MESSAGE", $replyto['message']);
		
		// set owner
		$fullname = formatName(NULL, $replyto);
		$email = formatEmail($replyto);
			
		//create owner email address
		if($email)
			$owner = "<a href=\"".$_SESSION['mailhandle'].$email."\">$fullname</a>";
		else
			$owner = $fullname;
		
		$this->set_var("REPLY_SUBMITTED_BY", $owner);
		$this->set_var("REPLY_DATE", $replyto['date']);
		
		$this->t_body->parse("reply_to_block", "reply_to");
		
		//dont keep putting Re: at front if it already there
		if(strncasecmp($replyto['heading'], 'Re:', 3))
			$this->set_var("HEADING", "Re: ".$replyto['heading']);
		else
			$this->set_var("HEADING", $replyto['heading']);

	}
	else
	{
		$this->set_var("reply_to_block", "");
		$this->set_var("HEADING", "");
	}
	
	$this->set_var("ID", 0);
	$this->set_var("CID", $cid);
	$this->set_var("PARENT", $parent);
		
	$this->set_var("BODY", "");

	$this->set_var("delete_button_block", "");
}

function putExisting($item) {

	$this->set_var("reply_to_block", "");
	$this->set_var("ID", $item['id']);
	$this->set_var("CID", $item['cid']);
	$this->set_var("PARENT", $item['parent']);
	$this->set_var("HEADING", $item['heading']);
	$this->set_var("BODY", $item['message']);
	$this->t_body->parse("delete_button_block", "delete_button");
}

function set_var($varName, $varContents)
{
    $this->t_body->set_var($varName, $varContents);
}

function showPage()
{
    $this->t_body->pparse("finaloutput", "tplBODY");
}

}


$myPage = new pageTpl();
$forum = new forum();


if(isset($_POST['id']) && $_POST['id'] && isset($_POST['delete']) && $_POST['delete'] == "Delete Post")
{
	$msg = $forum->getSinglePost($_POST['id']);

	//check if modifier ownes post he deleting or moderator
	if($_SESSION['id'] == $msg['owner'] || perm($msg['cid'], 1) > 1)
	{
		$forum->delPost($_POST['id']);	
		localRedir("/forum/viewForum.php?cid=".$msg['cid']);
	}
	else
		error(29);
}
else if(isset($_POST['heading']) && $_POST['heading'] && isset($_POST['body']) && $_POST['body'] && isset($_POST['id']) && $_POST['id'])
{
	//modify post

	$msg = $forum->getSinglePost($_POST['id']);

	//check if modifier ownes post he modifing or moderator
	if($_SESSION['id'] == $msg['owner'] || perm($msg['cid'], 1) > 1)
	{
		$forum->modPost($_POST['id'], $_POST['heading'], $_POST['body']);
		localRedir("/forum/viewForum.php?cid=".$_POST['cid']);
	}
	else
		error(29);
}
else if($_POST['heading'] && $_POST['body'] && $_POST['cid'] && isset($_POST['parent']))
{
	//add post
	if(perm($_POST['cid'], 1) > 0)
	{
		$forum->addPost($_POST['cid'], $_SESSION['id'], $_POST['parent'], $_POST['heading'], $_POST['body']);
	
		//output code to send the browser to where it came from
		$back = -2;
		include("../include/back.php");
		exit;
	}
	else 
		error(28);
	
}
else if (isset($_GET['id']) && $_GET['id'])
{
	$item = $forum->getSinglePost($_GET['id']);

	//check securit perms to edit post
	
	if($item['owner'] == $_SESSION['id'] || perm($item['cid'], 1) > 1)
	{
		$myPage->putExisting($item);
	}
	else
		error(29);

} 
else if(isset($_GET['cid']) && $_GET['cid'] && isset($_GET['parent']))
{
	if(perm($_GET['cid'], 1) > 0)
	{
		$myPage->putEmpty($_GET['cid'], $_GET['parent']);
	}
	else
		error(28);
}
$myPage->showPage();

?>
