<?php

//script used for downloading mail attachments

//includes
require_once("../include/session.php");
require_once("../include/include.php");
require_once("mail.php");
require_once("mail_parser.php");
	
if(isset($_GET['msgid']) && $_GET['msgid'] && (isset($_GET['pid']) && $_GET['pid']) || (isset($_GET['cid']) && $_GET['cid']))
{
	//create mail object
	$mail = new Mail;

	//check user info
	if(!$mail->checkMailDetails()){
		error(4);
	}
	//open mailbox
	$inbox = $mail->openMailBox();

	$email = new mail_parser($inbox, $_GET['msgid']);

	if($_GET['cid'])
	{
		//convert cid into pid (magic)
		$pid = $email->convertCIDtoPID($_GET['cid']);
	}	
	else
		$pid = $_GET['pid'];

	//search the email structure for the required pid and return the listed variables
	$variables = array('type', 'encoding', 'dparameters[0]->value', 'parameters[0]->value');
	$file = $email->variableSearch($pid, $variables, $email->email);

	$body = imap_fetchbody($inbox, $_GET['msgid'], $pid);

	if($_GET['save'])
		$attach = "attachment;";
	else
		$attach = "";

	
	//filename
	if($file['dparameters[0]->value'] != "")
		$filename = $file['dparameters[0]->value'];
	else
		$filename = $file['parameters[0]->value'];

	//send headers
	if($file['type'])
		header ("Content-Type: {$file['type']}"); 
	else
		header ("Content-Type: application/octet-stream"); 

	header ("Content-Disposition: $attach filename=\"".$filename."\""); 

	$body = $email->decodeMail($file['encoding'], $body);

	echo $body;	

}
else 
	error(2);	
?>
