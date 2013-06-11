<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//outputs form to get users mail details and processes the information

//require_once("../include/session.php");
//require_once("../include/include.php");

require_once("../../include/SitePage.php");
require_once('Mail.php');
require_once('Mail/mime.php');

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

	/* Validate From email address
	 */
	if (strlen($user['alt_email'])) {
		$email = new Email($user['alt_email']);

		if ($email->isValid()) {
			$from_email = $name.'<'.$user['alt_email'].'>';
		}
		else {
			error(42);
		}
	}
	else if ($user['local_email'] || Cfg::get('site_location') == 'wenona') {
		$email = new Email($user['username'] . '@' . Cfg::get('mail_address'));

		if ($email->isValid()) {
			$from_email = $name . '<' . $email->getEmail() . '>';
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

		if($toemail['alt_email'])
			$to = $toemail['alt_email'];
		else if($user['local_email'] || Cfg::get('site_location') == 'wenona')
			$to = $toemail['username']."@".Cfg::get('mail_address');
		else
			error(43);
	}

	//if emails have ; seperator account for it
	$to = str_replace(";",",",$to);


	//check if its got no to address but has either cc or bcc
	//put the users email in the to field
	if( !stristr($to,'@') && ( $bcc || $cc) )
	{
		$to = '"'.$to.'" <'.$user['username'].'@'.Cfg::get('mail_address').'>';
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
		$recipients['Cc'] = $cc;
	}

	if(strrchr($bcc, '@'))
	{
		$bcc = str_replace(";",",",$bcc);
		$recipients['Bcc'] = $bcc;
	}

	$mime = new Mail_mime("\r\n");

	$mime->setHTMLBody($message);

	if (is_array($_FILES)) {
		foreach ($_FILES["file"]["error"] as $key => $error) {
			if ($error == UPLOAD_ERR_OK) {
				$mime->addAttachment($_FILES['file']['tmp_name'][$key], $_FILES['file']['type'][$key], $_FILES['file']['name'][$key]);
			}
		}
	}

	$body = $mime->get(array(
		'html_charset' => 'utf-8',
		'text_charset' => 'utf-8',
		'head_charset' => 'utf-8',
	));

	if(Cfg::get('mail_smtp_host'))
		$params['host'] = Cfg::get('mail_smtp_host');

	$mail =& Mail::factory('smtp', $params);

	$hdrs = $mime->headers($headers);

	$return = $mail->send($recipients, $hdrs, $body);
	if (PEAR::isError($return))
		error(9, $return->getMessage());
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
