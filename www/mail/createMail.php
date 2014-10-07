<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 ************************************************************/
//enable users to send email
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$page = new SitePage();

$groupHandler = new Group;

if (Cfg::get('mail_enable') == 1) {

    // Parse out list of emails passed
    // The name needs to be included in both the ID and value
    $replytoList = array();
    $bccList     = array();
    foreach (Mailer::parseAddressString($_REQUEST['replyto']) as $email => $name) {
        $replytoList[] = Mailer::buildAddress($email, $name);
    }
    foreach (Mailer::parseAddressString($_REQUEST['bcc']) as $email => $name) {
        $bccList[] = Mailer::buildAddress($email, $name);
    }

    $page->setFile(TEMPLATE_content, 'mail/send.html');
    $page->setTitle('Webmail');
    $page->setTab('webmail');

    $output = array(
        'SUBJECT' => '',
        'BODY'    => '',
        'REPLYTO' => json_encode($replytoList),
        'BCC'     => json_encode($bccList),
    );

    $zone = new \Schoolbox\Resource\Attachment\Zone('email-attachments');
    $zone->setMulti(true)
        ->setThumbnailSize(64)
        ->setModalTitle('Select files')
        ->setMimeFilter('*/*')
        ->setAttachPrompt('Click or drag to add attachments');

    $zone->renderIntoSitePage($page, 'ATTACH_ZONE');

    $page->setVar($output);
    $page->outputPage();
} else {
    error(7);

}