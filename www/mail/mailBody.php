<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//outputs mail body only

require_once("../include/session.php");
require_once("../include/include.php");
require_once("mail.php");
require_once("mail_parser.php");
		

$mail = new Mail;
$mail->checkMailDetails();
$inbox = $mail->openMailBox();
$email = new mail_parser($inbox, $_GET['msgid']);
echo $email->getBody("HTML");
imap_close($inbox);

?>

