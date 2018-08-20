<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 ************************************************************/
//enable users to send email
$kernel = require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/Bootstrap.php';

$page = new SitePage();
$page->setDi($kernel->getDependencyInjector());

$groupHandler = new Group;

if (Cfg::get('mail_enable') == 1) {

    // Parse out list of emails passed
    // The name needs to be included in both the ID and value
    $replyToList = [];
    $ccList = [];
    $bccList = [];
    $invalidEmails = [];

    if ($_REQUEST['replyto']) {
        $replyTo = Mailer::parseAddressString($_REQUEST['replyto'], false);

        foreach ($replyTo['valid'] as $email => $name) {
            $replyToList[] = Mailer::buildAddress($email, $name);
        }

        $invalidEmails[] = $replyTo['invalid'];
    }

    if ($_REQUEST['cc']) {
        $cc = Mailer::parseAddressString($_REQUEST['cc'], false);
        foreach ($cc['valid'] as $email => $name) {
            $ccList[] = Mailer::buildAddress($email, $name);
        }

        $invalidEmails[] = $cc['invalid'];
    }

    if($_REQUEST['bcc']) {
        $bcc = Mailer::parseAddressString($_REQUEST['bcc'], false);
        foreach ($bcc['valid'] as $email => $name) {
            $bccList[] = Mailer::buildAddress($email, $name);
        }

        $invalidEmails[] = $bcc['invalid'];
    } else {
        $bccList[] = Mailer::buildAddress($_SESSION['user']->getEmail(), $_SESSION['user']->getFullName());
    }

    $page->setFile(TEMPLATE_content, 'mail/send.html');
    $page->setTitle('Webmail');
    $page->setTab('webmail');

    $invalidEmails = array_reduce(array_filter($invalidEmails), 'array_merge', []);

    $page->setBlock(TEMPLATE_content, 'alert_box', 'alert_box_block');

    if (\count($invalidEmails) > 0) {
        $page->setBlock('alert_box', 'invalid_address', 'invalid_address_block');
        foreach ($invalidEmails as $email) {
            // Check if the email is just in between the <> symbol and assign it to variable on purpose
            if ($emailFromString = Mailer::getEmailFromString($email)) {
                $email = $emailFromString;
            }
            $username = explode('@', $email);
            $page->setVar('INVALID_NAME', $username[0]);
            $page->setVar('INVALID_EMAIL', $email);
            $page->parse('invalid_address_block', 'invalid_address', true);
        }
        $page->parse('alert_box_block', 'alert_box', false);
    } else {
        $page->setVar('alert_box_block', '');
    }

    $output = array(
        'SUBJECT' => $_REQUEST['subject'] ? Scrub::text($_REQUEST['subject']) : '',
        'BODY'    => '',
        'REPLYTO' => json_encode($replyToList),
        'CC'      => json_encode($ccList),
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
