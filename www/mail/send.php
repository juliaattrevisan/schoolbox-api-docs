<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//outputs form to get users mail details and processes the information

require_once("../include/session.php");
require_once("../include/include.php");
require_once("mail.php");

class OutPut
{
	function OutPut()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "mail/send.htm");

		$this->t_body->set_var("TITLE", HEADING." - Send Mail");
		
		include_once("../include/page.php");
	}

	function sendMail($to, $cc, $bcc, $subject, $message)
	{
		//get users email address to use in from field
		
		$db = new dbManager();
		$db->dbConnect();

		$query = "SELECT firstname, lastname, username, local_email, alt_email FROM user WHERE id = '".$_SESSION["id"]."'";

		$db->dbDoQuery($query);
		//check if this returned an email address to attach as the from
		if($db->dbCountRows() < 1)
		{ 
			error(7);
		}
		$user = $db->dbGetNextArray();

		if($user['alt_email'])
			$from_email = $user['alt_email'];
		else if($user['local_email'])
			$from_email = $user['username']."@".MAIL_address;
		else
			error(42);

		//if emails have ; seperator account for it
		$to = str_replace(";",",",$to);
		$cc = str_replace(";",",",$cc);
		$bcc = str_replace(";",",",$bcc);


		//check if its got no to address but has either cc or bcc
		//put the users email in the to field
		if( !strrchr($to, '@') && ( $bcc || $cc) )
		{
			$to = $to." <".$user['username']."@".MAIL_address.">";
		}

		//create email header, we put to in header because the funtion is broken on some servers
		$headers = "To: $to\r\n". 
			"From: ".$user['firstname']." ".$user['lastname']." <".$from_email.">\r\n".
			"Reply-To: ".$user['firstname']." ".$user['lastname']." <".$from_email.">\r\n";
		
		if($cc != ""){ $headers .= "Cc: $cc\r\n"; } 
		if($bcc != ""){ $headers .= "Bcc: $bcc\r\n"; } 
		
		//this is a hack at getting bounced messages to return to sender
		$params = "-f".$user['username']."@".MAIL_address;

		//fix qoute escapes
		$message = str_replace("\'", "'", $message);
		$message = str_replace("\\\"", "\"", $message);
		$subject = str_replace("\'", "'", $subject);
		$subject = str_replace("\\\"", "\"", $subject);

		// if attachments exist
		if($_FILES['attachment']['name'])
		{
			$body = $message;
			// create a MIME boundary string
			$boundary = "====== ".md5(uniqid(time()))." =====";
			// add MIME data to the message headers
			$headers .= "MIME-Version:1.0\r\n";
			$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";

			// start building a MIME message

			// first part is always the message body
			// encode as 7-bit text
			$message = "--".$boundary."\r\n";
			$message .= "Content-Type: text/plain; charset=\"us-ascii\"\r\n";
			$message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
			$message .= "$body\r\n\r\n";
		
			//next we add the attachment
			$fp = fopen($_FILES['attachment']['tmp_name'], "rb");
			$data = fread($fp, $_FILES['attachment']['size']);
			$data = chunk_split(base64_encode($data));
			fclose($fp);

			// add the MIME data
			$message .= "--" . $boundary . "\r\n";
			$message .= "Content-Type: " . $_FILES['attachment']['type'] . ";\r\n\tname=\"" .$_FILES['attachment']['name'] . "\"\r\n";
			$message .= "Content-Transfer-Encoding: base64\r\n";
			$message .= "Content-Disposition: attachment; \r\n\tfilename=\"" .$_FILES['attachment']['name'] . "\"\r\n\r\n";
			$message .= $data . "\r\n";		
		}

		//now compose the message
		mail("", $subject, $message, $headers, $params); 
		
		//output code to send the browser to where it came from
		$back = -2;
		include("../include/back.php");
		exit;
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
		$this->t_body->set_var("SUBJECT", $subject);
		$this->t_body->set_var("BODY", $body);
		$this->t_body->set_var("REPLYTO", $replyto);
		$this->t_body->set_var("BCC", $bcc);
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

}

$myPage = new OutPut();

//if replyto has been specified
if(isset($_GET['reply']) && $_GET['reply'])
{
	$myPage->replyMail($_GET['reply'], $_GET['qoute']);
}
else if(isset($_GET['forward']) && $_GET['forward'])
{
	$myPage->forwardMail($_GET['forward']);
}
else if( isset($_POST['replyto']) && isset($_POST['bcc']) && $_POST['bcc'])
	$myPage->showPage($_POST['replyto'], $_POST['bcc']);
else if( (isset($_GET['replyto'])) && $_GET['replyto'])
	$myPage->showPage($_GET['replyto']);
else if( (isset($_POST['replyto'])) && $_POST['replyto'])
	$myPage->showPage($_POST['replyto']);
//if form has been submitted
else if(isset($_POST['to']) && $_POST['to'])
{
	$myPage->sendMail($_POST['to'], $_POST['cc'], $_POST['bcc'], $_POST['subject'], $_POST['message']);
}	
else
	$myPage->showPage();
?>
