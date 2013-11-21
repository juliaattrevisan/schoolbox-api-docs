<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 ************************************************************/
//enable users to send email
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

$page = new SitePage();

$groupHandler = new Group;


if (Cfg::get('mail_enable') == 1) {

    $page->setFile(TEMPLATE_content, 'mail/send.html');
    $page->setTitle('Webmail');
    $page->setTab('webmail');

    $output = array('SUBJECT' => '',
        'BODY' => '',
        'REPLYTO' => Scrub::text($_REQUEST['replyto']),
        'BCC' => Scrub::text($_REQUEST['bcc']),

    );

    $page->setVar($output);
    $page->outputPage();
} else {
    error(7);

}