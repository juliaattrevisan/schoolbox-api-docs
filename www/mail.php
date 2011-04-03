<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
*  Outputs to user a list of emails found in there mailbox. *
*  If user has not provided mail password or it is 	    *
*  incorrect the user is prompted for the password again    *
*							    *
*  $Id: mail.php,v 1.5 2004/01/24 05:35:48 jleckie Exp $							 			
*							    *
**********************************************************/

require_once $_SERVER['DOCUMENT_ROOT'] .'/../include/sitePage.php';

if(Cfg::get('mail_enable'))
{
	$page = new SitePage(); 
	
	$page->setFile(TEMPLATE_content, 'mail/roundcube.html');
	$page->setTitle('Webmail');
	$page->setTab('webmail');

	$params = explode('?', $_SERVER['REQUEST_URI']);
	$params = !empty($params[1]) ? "?$params[1]" : '';
			
	// Set template
	$page->setVar('ROUNDCUBE_PARAMS', $params);
	
	$page->outputPage();
	
	exit;
}
else
	error(19);

?>
