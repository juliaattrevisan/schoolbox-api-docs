<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
*  Outputs to user a list of emails found in there mailbox. *
*  If user has not provided mail password or it is 	    *
*  incorrect the user is prompted for the password again    *
*							    *
*  $Id: mail.php,v 1.5 2004/01/24 05:35:48 jleckie Exp $							 			
*							    *
************************************************************/

require_once("include/session.php");
require_once("include/include.php");
require_once("mail/mail.php");


class OutPut
{
	function OutPut()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "mail.htm");

		$this->t_body->set_var("TITLE", HEADING." - My Messages");
		
		$this->t_body->set_block("tplBODY", "mail_listing", "mail_block");
		
		$this->t_body->set_var('NAME', formatName();
		
		include("include/page.php");
	}

	function set_var($varName, $varContents)
	{
		$this->t_body->set_var($varName, $varContents);
	}



	function setMail($pos = 1)
	{
		include("mail/mail_parser.php");
	
		//pull in mail object
		global $mail;
		
		//open mailbox
		$inbox = $mail->openMailBox();
	
		// get number of messages
		//$total = imap_num_msg($inbox);
		
		$status = $mail->mailboxStatus($inbox);
		$this->set_var("USER", $mail->user);
		
		$total = $status->messages;
		//messages per page
		$increments = MAIL_increment;

		//only output 15 messages make links to all others
		$sections = ceil($total / $increments);
		$link = "";
		if($pos < 1)
			$pos = 1;

		for($x=0; $x < $sections; $x++)
		{
			$temp_start = ($x*$increments)+1;
			$temp_finish = ($x+1)*$increments;
			if($temp_finish > $total)
				$temp_finish = $total;

			//check where we currently are 
			if($pos < $temp_start || $pos > $temp_finish)
				$link .= "<a href=\"mail.php?pos=".$temp_start."\">".($x+1)."</a> ";
			else
			{
				//swap round so we get newest first..
				$start = $total-$temp_finish+1;
				$finish = $total-$temp_start+1;
				$link .= "<b>".($x+1)."</b> ";
			}
		}

		$this->set_var("TOTAL", $link);	
		if($total && $total > 0)
		{
			//iterate over messages
			for($x=$finish; $x>($start-1); $x--)
			{
				$email = new mail_parser($inbox, $x);
				if(is_object($email->header)) {
					$count++;
					//set all the variables
					$this->set_var("CHECKBOX_NAME", "dmsg[]");
					$this->set_var("CHECKBOX_VALUE", $x);				
					
					//check if message has been read
					if($email->header->Unseen == 'U' || $email->header->Recent == 'N')
						$this->set_var("STYLE", "font-weight: bold;");
					else
						$this->set_var("STYLE", "");
	
					//format date apppropiately
					$date = strtotime($email->header->Date);
					$this->set_var("DATE", date('D j/m/y g:i A', $date));
	
					//if user has a personal name output that otherwise just output there email address
					if($email->header->from[0]->personal)
						$from = $email->header->from[0]->personal;
					else
						$from = $email->header->from[0]->mailbox . "@" . $email->header->from[0]->host;
					$this->set_var("FROM", scrubTEXT($from));
	
					//$this->set_var("FROM", htmlspecialchars($email->header->fromaddress));
					//correction if no subject
					if ($email->header->Subject == "")
						$this->set_var("SUBJECT", "<a href=\"/mail/view_mail.php?msgid=$x\">No Subject</a>");
					else
						$this->set_var("SUBJECT", "<a href=\"/mail/view_mail.php?msgid=$x\">".scrubTEXT($email->decodeHeader($email->header->Subject))."</a>");
						
					// parse structure to see if attachments exist
					// display icon if so
					if(sizeof($email->getAttachmentList()) > 0)
						$this->set_var("ATTACHMENT", "<img src=\"/images/paperclip.gif\" width=\"26\" height=\"24\">");
					else
						$this->set_var("ATTACHMENT", "&nbsp;");
					$this->t_body->parse("mail_block", "mail_listing", true);
				}
			}
			//clean up
		}
		else
			$this->set_var("mail_block", "</table>You have no mail at this time<table>");
		
		imap_close($inbox);
	}

	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}

if(MAIL_enable)
{
	$myPage = new OutPut();
	$mail = new Mail;

	if($mail->checkMailDetails())
	{
		if($_POST['purgebtn'])
			$mail->purgeMailBox();
		if($_POST['dmsg'])
			$mail->deleteMailArray($_POST['dmsg']);
		else
			$myPage->setMail($_GET['pos']);  //display mail at section pos
	}
	else
	{
		$mail->submitMailDetails();
		exit;
	}

	$myPage->showPage();
}
else
	error(19);

?>
