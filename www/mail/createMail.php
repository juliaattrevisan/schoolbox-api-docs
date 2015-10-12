<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 ************************************************************/
//enable users to send email
$kernel = require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$page = new SitePage();
$page->setDi($kernel->getDependencyInjector());

$groupHandler = new Group;

if (Cfg::get('mail_enable') == 1) {

    // Parse out list of emails passed
    // The name needs to be included in both the ID and value
    $replytoList = array();
    $bccList     = array();
    foreach (Mailer::parseAddressString($_REQUEST['replyto']) as $email => $name) {
        $replytoList[] = Mailer::buildAddress($email, $name);
    }
    if($_REQUEST['bcc']) {
        foreach (Mailer::parseAddressString($_REQUEST['bcc']) as $email => $name) {
            $bccList[] = Mailer::buildAddress($email, $name);
        }
    } else {
        $bccList[] = Mailer::buildAddress($_SESSION['user']->getEmail(), $_SESSION['user']->getFullName());
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
        ->setAttachPrompt('Attach files');

    $zone->renderIntoSitePage($page, 'ATTACH_ZONE');

    $page->setVar($output);
    $page->outputPage();
} else {
    error(7);

}