<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//outputs form to get users mail details and processes the information

require_once __DIR__."/../../include/Bootstrap.php";

$page = new SitePage();

define('MAX_RECIPIENTS', 10);

function sendMail($to, $cc, $bcc, $subject, $message)
{
	//get users email address to use in from field

	$db = DeprecatedDatabase::getInstance();
	$db->dbConnect();

	$userHandler = new User();

	$query = "SELECT firstname, lastname, username, local_email, title, alt_email FROM user WHERE id = '".$_SESSION["id"]."'";
	$user = $db->dbGetSingleRow($query);

	$name = $userHandler->formatName($user);

	$mail = new Swift_Message();

	/* Validate From email address
	 */
	if (strlen($user['alt_email'])) {
		$email = new Email($user['alt_email']);

		if ($email->isValid()) {
			$mail->addFrom($user['alt_email'], $name);
			$mail->addReplyTo($user['alt_email'], $name);
		}
		else {
			error(42);
		}
	}
	else if ($user['local_email'] || Cfg::get('site_location') == 'wenona') {
		$email = new Email($user['username'] . '@' . Cfg::get('mail_address'));

		if ($email->isValid()) {
			$mail->addFrom($email->getEmail(), $name);
			$mail->addReplyTo($email->getEmail(), $name);
		}
		else {
			error(42);
		}
	}
	else {
		error(42);
	}

	//if id is submitted to form convert to email
	if(is_numeric($to)) {
		$query = "SELECT username, local_email, alt_email FROM user WHERE id = $to";
		$toemail = $db->dbGetSingleRow($query);

		if ($toemail['alt_email']) {
			$mail->addTo($toemail['alt_email']);
		}
		elseif ($user['local_email'] || Cfg::get('site_location') == 'wenona') {
			$mail->addTo($toemail['username']."@".Cfg::get('mail_address'));
		}
		else {
			error(43);
		}
	}
	else {
		//if emails have ; seperator account for it
		$to = str_replace(";",",",$to);
		foreach (Mailer::parseAddressString($to) as $address => $name) {
			$mail->addTo($address, $name);
		}
	}

	//check if its got no to address but has either cc or bcc
	//put the users email in the to field
	if (!stristr($to,'@') && ($bcc || $cc)) {
		$mail->addTo($user['username'].'@'.Cfg::get('mail_address'), $to);
	}

	//fix qoute escapes
	if (get_magic_quotes_gpc())
	{
		$message = stripslashes($message);
		$subject = stripslashes($subject);
	}

	//create email header
	$mail->setSubject($subject);

	if (strrchr($cc, '@')) {
		$cc = str_replace(";",",",$cc);
		foreach (Mailer::parseAddressString($cc) as $address => $name) {
			$mail->addCc($address, $name);
		}
	}

	if (strrchr($bcc, '@')) {
		$bcc = str_replace(";",",",$bcc);
		foreach (Mailer::parseAddressString($bcc) as $address => $name) {
			$mail->addBcc($address, $name);
		}
	}

	$mail->setBody(strip_tags($message), null, 'utf-8');
	$mail->addPart($message, 'text/html', 'utf-8');

	if (is_array($_FILES)) {
		foreach ($_FILES["file"]["error"] as $key => $error) {
			if ($error == UPLOAD_ERR_OK) {
				$attach = Swift_Attachment::fromPath($_FILES['file']['tmp_name'][$key]);
				$attach->setFilename($_FILES['file']['name'][$key]);
				$attach->setContentType($_FILES['file']['type'][$key]);
				$mail->attach($attach);
			}
		}
	}

	try {
		Swift_Mailer::newInstance(Mailer::getSwiftTransport())->send($mail);
	}
	catch (Exception $ex) {
		error(9, $ex->getMessage());
	}
}


//if form has been submitted
if(isset($_POST['to']) && $_POST['to'] && Cfg::get('mail_enable'))
{
	sendMail($_POST['to'], $_POST['cc'], $_POST['bcc'], $_POST['subject'], $_POST['message']);

	//output code to send the browser to where it came from
	$back = -2;
	include("../include/back.php");
	exit;
}
else
{
	SitePage::localRedir('/mail/createMail.php');
}
