<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//outputs form to get users mail details and processes the information

require_once("../include/session.php");
require_once("../include/include.php");
require_once('Mail.php');
require_once('Mail/mime.php');

define('MAX_RECIPIENTS', 10);

function sendMail($to, $cc, $bcc, $subject, $message)
{
	//get users email address to use in from field
	
	$db = new dbManager();
	$db->dbConnect();

	$query = "SELECT firstname, lastname, username, local_email, alt_email FROM user WHERE id = '".$_SESSION["id"]."'";
	$user = $db->dbGetSingleRow($query);

	if($user['alt_email'])
		$from_email = $user['alt_email'];
	else if($user['local_email'] || SITE_location == 'wenona')
		$from_email = $user['username']."@".MAIL_address;
	else 
		error(42);

	//if id is submitted to form convert to email
	if(is_numeric($to) || SITE_location == 'wenona') {
		$query = "SELECT username, local_email, alt_email FROM user WHERE id = $to";
		$toemail = $db->dbGetSingleRow($query);

		if($toemail['alt_email'])
			$to = $toemail['alt_email'];
		else if($user['local_email'] || SITE_location == 'wenona')
			$to = $toemail['username']."@".MAIL_address;
		else 
			error(43);
	}

	//if emails have ; seperator account for it
	$to = str_replace(";",",",$to);


	//check if its got no to address but has either cc or bcc
	//put the users email in the to field
	if( !isEmail($to) && ( $bcc || $cc) )
	{
		$to = '"'.$to.'" <'.$user['username'].'@'.MAIL_address.'>';
	}

	//fix qoute escapes
	if (get_magic_quotes_gpc())
	{
		$message = stripslashes($message);
		$subject = stripslashes($subject);
	}

	//create email header
	$headers = 	array(	'To' 		=> $to,
						'From' 		=> $from_email,
						'Subject'   => $subject,
						'Reply-To'	=> $from_email,
						);
						
	$recipients['To'] = $to;
											
	if(strrchr($cc, '@'))
	{
		$cc = str_replace(";",",",$cc);
		$headers['Cc'] = $cc;
	}
	
	if(strrchr($bcc, '@'))
	{
		$bcc = str_replace(";",",",$bcc);
		$headers['Bcc'] = $bcc;
	}

	$mime = new Mail_mime("\r\n");

	$mime->setHTMLBody($message);
	
	foreach ($_FILES["file"]["error"] as $key => $error) {
   		if ($error == UPLOAD_ERR_OK) {
			$mime->addAttachment($_FILES['file']['tmp_name'][$key], $_FILES['file']['type'][$key], $_FILES['file']['name'][$key]);
		}
	}

	$body = $mime->get();
	
	if(MAIL_smtp_server)
		$params['host'] = MAIL_smtp_server;
	
	$mail =& Mail::factory('smtp', $params);
	
	$hdrs = $mime->headers($headers);
	$return = $mail->send($recipients, $hdrs, $body);
	if($return != true)
		error(9, $return->getMessage()); 
}


//if form has been submitted
if(isset($_POST['to']) && $_POST['to'] && MAIL_enable)
{
	sendMail($_POST['to'], $_POST['cc'], $_POST['bcc'], $_POST['subject'], $_POST['message']);
	
	//output code to send the browser to where it came from
	$back = -2;
	include("../include/back.php");
	exit;
}	
else
{
	localRedir('/mail/createMail.php');
}
?>
