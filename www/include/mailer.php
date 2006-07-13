<?php

require_once('Mail.php');
require_once('Mail/mime.php');
require_once('template.php');

class Mailer {
function Mailer($template, $subject = "", $to="") {
	
	//setup header
	$this->headers = '';
	$this->setFrom(FROM_address, FROM_name);
	$this->headers['MessageID'] = '<'.md5(mktime()).'@lektecmailer>';
	
	$this->setTo($to);
	$this->setSubject($subject);

	//setup HTML template
	$this->template = new Template(TPL_directory);
	$this->t_label = "tplBODY";
	$this->loadTemplate($template);

	//setup defaults

	//setup MIME email
	$this->email = new Mail_mime("\r\n");

	//$this->text = "Please view this email in a HTML capable client.";
}

function setFrom($email, $name="") {
	if($name) {
		$this->headers['From'] = $name .' <'.$email.'>'; 
		$this->headers['Return-Path']  = $email;
	}
	else {
		$this->headers['From'] = $email;
		$this->headers['Return-Path']  = $email;
	}
}

function setTo($to) {
	if(is_array($to)) {
	
		if($to['email']) {
			if($to['name'])
				$this->to = $to['name'] .' <'.$to['email'].'>'; 
			else
				$this->to = $to['email'];
		}
		else {
		
			$this->to = '';
			foreach($to as $email) {
				if($email['name'])
					$this->to .= $email['name'].' <'.$email['email'].'>,';
				else
					$this->to .= $email['email'].',';
			}
			$this->to= substr($this->to, 0, -1);  //remove trailing ,
		}
	}
	else
		$this->to = $to;

	$this->headers['To'] = $to;
}

function setSubject($subject) {
	$this->headers['Subject'] = $subject;
}

function loadTemplate($file) {
	$this->template->set_file($this->t_label, $file);
}

function setVar($name, $value='') {
	$this->template->set_var($name, $value);
}

function setBlock($parent, $handle, $name) {
	$this->template->set_block($parent, $handle, $name);
}
	
function parse($target, $handle, $append = false) {
	$this->template->parse($target, $handle, $append);
}

function addFile($file, $type = 'text/plain', $filename = '', $isFile = true) {
	//add attachment to email
	$this->email->addAttachment($file, $type, $filename, $isFile);
}

function addImage($file, $type = 'application/octet') {
	//add inline image to email
	$this->email->addHTMLImage($file, $type);
}

function composeHTML() {
		//initialise the email document
		return $this->template->parse("final", $this->t_label);
}

function printEmail() {
	print $this->composeHTML();
}

function send($to="") {
	
	if($to)
		$this->setTo($to);
	
	//construct body
	//$this->email->setTXTBody($this->text);
	$this->email->setHTMLBody($this->composeHTML());
	
	//retrieve email
	$body = $this->email->get();
	$this->email->_headers['To'] = $this->to;	//	mime_mail doe not overwrite its headers with the ones you send
	$hdrs = $this->email->headers($this->headers);

	//send email
	$mail =& Mail::factory('smtp');
	$mail->send($this->to, $hdrs, $body);
}

}

?>
