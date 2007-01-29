<?php

require_once( 'include/include.php');
require_once( 'include/dbManager-mysql.php');
require_once( 'mail/mail.php');

define( '_MAX_STRLEN', '2048');

class FeedbackPage
{
	function FeedbackPage( $showForm)
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplFeedback", "feedback.htm");			
		$this->t_body->set_var("TITLE", HEADING." - Feedback");

		if( $showForm == true) {
			$this->t_body->set_var( 'FORM_BEFORE', '');
			$this->t_body->set_var( 'FORM_AFTER', '');
		}
		else {
			$this->t_body->set_var( 'FORM_BEFORE', '<!--');
			$this->t_body->set_var( 'FORM_AFTER', '-->');
		}
	}


	function setOutput($fail)
	{
		//on failure print out message
		$this->setVar("ERROR", $fail);
	
	}

	function setVar($varName, $varContents)
	{
		$this->t_body->set_var($varName, $varContents);
	}
	
	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplFeedback");
	}
}


session_start();	

# Add check that referer is this server (spoofable but better than not)
#if( $_REQUEST[ 'submit'] and ($_SERVER[ 'HTTP_REFERER'] == $_SERVER[ 'REMOTE_ADDR']) {

if( $_REQUEST[ 'submit']) {

	$myPage = new FeedbackPage( false);

	if( !checkArgs()) {
		$myPage->setOutput( "<p>Sorry but we couldn't send your feedback, most likely because it was too long.<br>Please <a href='feedback.php'>try again</a>.</p>");
	}
	else {
		if( isset( $_REQUEST[ 'username'])) {
			$from = $_REQUEST[ 'username'];
		}
		else {
			$from = _EMAIL_FROM_DEFAULT;
		}

		if( isset( $_REQUEST[ 'deakin'])) {
			$to = _EMAIL_TO_DEAKIN;
		}
		else {
			$to = _EMAIL_TO;
		}

		$message = str_replace( "\'", "'", $_REQUEST[ 'body']);

		sendMail( $message, $from, $to);

		if( $_SESSION[ 'SESSION'] <> '') {
			$link = '<a href="home.php">return to portal</a>';
		}
		else {
			$link = '<a href="login.php">return to login</a>';
		}


		$myPage->setOutput( "<p>Your feedback has successfully been sent, thank you.</p>$link");
	}


}
else {
	$myPage = new FeedbackPage( true);
	$myPage->setOutput("");

	if( $_SESSION[ 'username'] <> '') {
		$myPage->setVar( 'username', $_SESSION[ 'username']);
	}
	else {
		$myPage->setVar( 'username', '');
	}
}

$myPage->showPage();



	# Check the arguments passed were sane
function checkArgs() {
	foreach( array_keys( $_REQUEST) as $key) {
		if( strlen( $_REQUEST[ $key]) > _MAX_STRLEN) {
			return false;
		}
	}
	return true;
}

	# Send the email
function sendMail( $message, $from, $to) {
	$headers = "From: $from\r\n";

	$subject = _EMAIL_SUBJECT;

	mail( $to, $subject, $message, $headers);
}

		
?>
