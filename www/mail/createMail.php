<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//outputs form to get users mail details and processes the information

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/permission.php");
require_once("mail.php");

class OutPut
{
	function OutPut($type)
	{
		$this->t_body = new Template(TPL_directory);
		$this->type = $type;
		if($this->type == "simple")
		{
			$this->t_body->set_file("tplBODY", "mail/send.htm");
		}
		else
		{
			$this->t_body->set_file("tplBODY", "mail2.htm");
		}
		$this->t_body->set_var("TITLE", HEADING." - Send Mail");
		
		include_once("../include/page.php");
	}

	function forwardMail($msgid)
	{
		//reply to the email with the the $msgid
		//first include the mail class
		//this opens the mailbox
		$mail = new Mail;		
		$mail->checkMailDetails();
		$inbox = $mail->openMailBox();

		//get email		
		include("mail_parser.php");
		$email = new mail_parser($inbox, $msgid);

		$subject = "Fw: " . $email->decodeHeader($email->header->Subject);
		
		// display message body with forward symbol >
		$body = "\n\n";
		$body .= ">From: ".$email->header->fromaddress."\n";
		$body .= ">To: ".$email->header->toaddress."\n";

		if($email->header->ccaddress)
		{
			$body .= ">Cc: ".$email->header->ccaddress."\n";
		}

		$body .= ">Date: ".$email->header->Date."\n";
		$body .= ">Subject: " . $email->decodeHeader($email->header->Subject)."\n\n>";

		$text = $email->getBody("TEXT_ONLY");
		$body .= str_replace("\n", "\n>", $text);

		$body = str_replace("\'", "'", $body);
	
		$this->showPage("", "", $subject, $body);
	}

	function replyMail($msgid, $qoute = 0)
	{
		//reply to the email with the the $msgid
		//first include the mail class
		$mail = new Mail;		
		//print "opening mailbox";
		$mail->checkMailDetails();
		$inbox = $mail->openMailBox();

		//get email		
		include("mail_parser.php");
		$email = new mail_parser($inbox, $msgid);

		// check for Reply-To: header and use if available
		if($email->header->reply_toaddress)
			$to = trim($email->header->reply_toaddress);
		else
			$to = trim($email->header->fromaddress);

		//if subject already has Re: dont add another one
		if(strtolower(substr(trim($headers->Subject), 0, 3)) == "re:")
			$subject = $email->header->Subject;
		else
			$subject = "Re: " . $email->header->Subject;
		
		$body = "";

		//if qoute enabled put message in body
		if($qoute)
		{	
			
			$body = "\n\n\nOn " . $email->header->Date . ", you wrote: \n>";
			$text = $email->getBody("TEXT_ONLY");
			$body .= str_replace("\n", "\n>", $text);

		}

		$body = str_replace("\'", "'", $body);

		$this->showPage($to, "", $subject, $body);	

	}


	function showPage($replyto = "", $bcc = "", $subject = "", $body = "")
	{
		if($this->type == "simple")
		{
			$this->t_body->set_var("SUBJECT", $subject);
			$this->t_body->set_var("BODY", $body);
			$this->t_body->set_var("REPLYTO", $replyto);
			$this->t_body->set_var("BCC", $bcc);
			$this->t_body->pparse("finaloutput", "tplBODY");
		}
		else
		{
			// Special passing requirment for roundcube
			$replyto = urlencode(htmlspecialchars($replyto));
			$bcc = urlencode(htmlspecialchars($bcc));
			$this->t_body->set_var("ROUNDCUBE_PARAMS", "?_task=mail&_action=compose&_to=$replyto&_bcc=$bcc&_subject=$subject&_body=$body");
			$this->t_body->pparse("finaloutput", "tplBODY");
		}
	}

}


	
if(MAIL_interface == "simple" || (SITE_location == 'mgs' && !isStudent($_SESSION['gid'])) )
{	 
	$myPage = new OutPut('simple');
	
	//if replyto has been specified
	if(isset($_GET['reply']) && $_GET['reply'])
		$myPage->replyMail($_GET['reply'], $_GET['qoute']);
	else if(isset($_GET['forward']) && $_GET['forward'])
		$myPage->forwardMail($_GET['forward']);
	else if($_REQUEST['replyto'] || $_REQUEST['bcc'])
		$myPage->showPage($_REQUEST['replyto'], $_REQUEST['bcc']); 
	else if(SITE_location != 'wenona')
		$myPage->showPage();
	else
		error(2);
}
else
{
	$myPage = new OutPut(MAIL_interface);
	
	if($_REQUEST['replyto'] || $_REQUEST['bcc'])
		$myPage->showPage($_REQUEST['replyto'], $_REQUEST['bcc']);
	else
		$myPage->showPage(); 
}