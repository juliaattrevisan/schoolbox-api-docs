<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//enable users to send email
require_once $_SERVER['DOCUMENT_ROOT'] .'/../include/sitePage.php';
$page = new SitePage(); 

if( ((SITE_location == 'mgs' || SITE_location == 'wenona') && !isStudent($_SESSION['gid'])) )
{	 
	
	$page->setFile(TEMPLATE_content, 'mail/send.html');
	$page->setTitle('Webmail');
	$page->setTab('webmail');
	
	$output = array('SUBJECT' 	=> '',
					'BODY'		=> '',
					'REPLYTO'	=> $_REQUEST['replyto'],
					'BCC'		=> $_REQUEST['bcc'],
					
				);
	
	$page->setVar($output);
	
}
else
{
	$page->setFile(TEMPLATE_content, 'mail/roundcube.html');
	$page->setTitle('Webmail');
	$page->setTab('webmail');
	
	// Special passing requirment for roundcube
	$replyto = urlencode(htmlspecialchars($_REQUEST['replyto']));
	$bcc = urlencode(htmlspecialchars($_REQUEST['bcc']));
	$page->setVar("ROUNDCUBE_PARAMS", "?_task=mail&_action=compose&_to=$replyto&_bcc=$bcc&_subject=$subject&_body=$body");
	
	
}

$page->outputPage();

?>