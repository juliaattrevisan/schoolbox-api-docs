<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//outputs mail.. also checks if user has submitted the mail account and password

require_once("../include/session.php");
require_once("../include/include.php");
require_once("mail.php");

class OutPut
{
	function OutPut()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "mail/view_mail.htm");

		$this->t_body->set_var("TITLE", HEADING." - View Mail");
		
		$this->t_body->set_block("tplBODY", "attachment_block", "attachment_listing");
		$this->t_body->set_block("tplBODY", "cc_block", "cc_block_var");

		include_once("../include/page.php");
	}

	function set_var($varName, $varContents)
	{
		$this->t_body->set_var($varName, $varContents);
	}

	function setControlPanel($total, $current)
	{
		$this->set_var("MSGPOS", ($total - $current));

		//previous button
		if($current == $total)
			$this->t_body->set_var("PREVIOUS", "&lt;&lt; previous");
		else
			$this->t_body->set_var("PREVIOUS", "<a href=\"/mail/view_mail.php?msgid=".($current+1)."\" class=\"mailcontrol\">&lt;&lt; previous</a>");

		//next button
		if(($current-1) < 1)
			$this->t_body->set_var("NEXT", "next &gt;&gt;");
		else
			$this->t_body->set_var("NEXT", "<a href=\"/mail/view_mail.php?msgid=".($current-1)."\" class=\"mailcontrol\">next &gt;&gt;</a>");

		//delete
		$this->t_body->set_var("DELETE", "/mail/view_mail.php?delete=$current");		
		
		//reply button	
		$this->t_body->set_var("REPLY", "/mail/send.php?reply=$current");
		//reply with quote button
		$this->t_body->set_var("REPLYQ", "/mail/send.php?reply=$current&qoute=1");
		//forward
		$this->t_body->set_var("FORWARD", "/mail/send.php?forward=$current");
	}

	function viewMail($msgid)
	{
		global $mail;

		//open the message and view the body
		$inbox = $mail->openMailBox();

		$total = imap_num_msg($inbox);
		$this->setControlPanel($total, $msgid);

		include("mail_parser.php");
		$email = new mail_parser($inbox, $msgid);

		//correction if no subject
		if ($email->header->Subject == "")
			$this->set_var("SUBJECT", "No Subject");
		else	
			$this->set_var("SUBJECT", $email->decodeHeader($email->header->Subject));
		
		$date = strtotime($email->header->Date);
		$this->set_var("DATE", date('l j/m/y g:i A', $date));
		
		//create from address and link (not sure if we need reply to here or not)
		$fromlink = $this->createEmailAddressLink(&$email->header->from[0]);
		$this->set_var("FROM", $fromlink);			

		$tolink = "";
		//ok now create the to and cc addresses and links
		foreach($email->header->to as $toAddressStruct)
		{
			//append comma between entries
			if($tolink != "")
				$tolink .= ", ";
			
			$tolink .= $this->createEmailAddressLink(&$toAddressStruct);
		}
		$this->set_var("TO", $tolink);		

		if(isset($email->header->ccaddress))
		{
			$cclink = "";
			foreach($email->header->cc as $toAddressStruct)
			{
				//append comma between entries
				if($cclink != "")
					$cclink .= ", ";
				
				$cclink .= $this->createEmailAddressLink(&$toAddressStruct);
			}
			
			$this->set_var("CC", $cclink);
			$this->t_body->parse("cc_block_var", "cc_block");
		}
		else
			$this->set_var("cc_block_var", "");

	
		$body = $email->getBody("HTML");

		$this->t_body->set_var("BODY", $body);

		$attachments = $email->getAttachmentList();

		//attachment handling
		if (sizeof($attachments) > 0 )
		{
			for($x=0; $x<sizeof($attachments); $x++)
			{
				$fileurl = "<li>";
				$fileurl .= "<a href=\"/mail/download.php?msgid=".$msgid."&pid=".$attachments[$x]['pid']."&save=1\">";
				$fileurl .= $attachments[$x]['name']."</a> (".ceil($attachments[$x]['size']/1024)." KB)";
				$this->set_var("ATTACHMENT", $fileurl);
				$this->t_body->parse("attachment_listing", "attachment_block", true);
			}
		}
		else
			$this->set_var("attachment_listing", "");	
		
		imap_close($inbox);
	}

	function createEmailAddressLink(&$part)
	{
		//given a structure for a email address from the imap_header (from, replyto, to, cc)
		//it will return a formatted email address

		$address = $part->mailbox . "@" . $part->host;

		if($part->personal)
		{
			$name = htmlspecialchars($part->personal);
			$addresslink =  "<a href=\"".$_SESSION['mailhandle'].$address."\">".$name."</a>";
		}	
		else
			$addresslink = "<a href=\"".$_SESSION['mailhandle'].$address."\">".$address."</a>"; 

		return $addresslink;
	}

	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}

$myPage = new OutPut();
$mail = new Mail;

if($mail->checkMailDetails())
{
	$msgid = $_GET['msgid'];

	if($_GET['delete'])
	{                                        
		//delete message then just go show the next one
		$msgid = $mail->deleteMailSingle($_GET['delete']);
		if($msgid == 0)   
			localRedir("/mail.php");
	}

	$myPage->viewMail($msgid);

}
else
	$mail->submitMailDetails();

$myPage->showPage();

?>
