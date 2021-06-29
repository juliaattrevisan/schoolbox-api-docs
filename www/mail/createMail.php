<?php

/**
 * Enable users to send email
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Mail\AbstractMailer;
use Alaress\Mail\Codec;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

$groupHandler = new Group();
$externalHandler = new External_User();
$userMapper = UserMapper::getInstance();

if (Config::get('mail_enable') == 1) {
    // Parse out list of emails passed
    // The name needs to be included in both the ID and value
    $invalidEmails = [];
    $addressLists = [
        'replyto' => [],
        'cc' => [],
        'bcc' => []
    ];

    // Only students' externalIds are passed in. Building emails from there.
    $buildEmailFromId = isset($_POST['selectedIds']) && !empty($_POST['selectedIds']);

    if ($buildEmailFromId) {
        $studentExternalIds = explode(',', $_POST['selectedIds']);
        $selectedGroups = isset($_POST['selectedGroups']) ? is_array($_POST['selectedGroups']) ? $_POST['selectedGroups'] : explode(',', $_POST['selectedGroups']) : [];
        /* Now get the parents of the students
         */
        if (in_array('parents', $selectedGroups)) {
            $addressLists['bcc'] = array_merge($addressLists['bcc'], $externalHandler->getParentEmails($studentExternalIds));
        }
        if (in_array('students', $selectedGroups)) {
            $users = $userMapper->findByColumn('synergy_id', $studentExternalIds);
            foreach ($users as $user) {
                $addressLists['bcc'][] = $user->getFormattedEmailDisplay();
            }
        }
        if (in_array('housemasters', $selectedGroups)) {
            $addressLists['bcc'] = array_merge($addressLists['bcc'], $externalHandler->getHousemasterEmails($studentExternalIds));
        }
    }

    foreach ($addressLists as $type => $request) {
        if (null !== $_REQUEST[$type]) {
            $emailAddresses = Mailer::parseAddressString($_REQUEST[$type], false);
            if (!empty($emailAddresses[AbstractMailer::KEY_VALID])) {
                foreach ($emailAddresses[AbstractMailer::KEY_VALID] as $email => $name) {
                    $addressLists[$type][] = Mailer::buildAddress($email, $name);
                }
            }
        } elseif (!$buildEmailFromId && null === $_REQUEST[$type] && $type === 'bcc') {
            $addressLists[$type][] = Mailer::buildAddress(
                $_SESSION['user']->getEmail(),
                $_SESSION['user']->getFullName()
            );
        }

        $invalidEmails[] = $emailAddresses[AbstractMailer::KEY_INVALID];
    }

    $page->setFile(TEMPLATE_content, 'mail/send.html');
    $page->setTitle('Webmail');
    $page->setTab('webmail');

    // Uniquify invalid email result
    $invalidEmails = array_unique(array_reduce(array_filter($invalidEmails), 'array_merge', []));

    $page->setBlock(TEMPLATE_content, 'alert_box', 'alert_box_block');

    if (\count($invalidEmails) > 0) {
        $page->setBlock('alert_box', 'invalid_address', 'invalid_address_block');
        foreach ($invalidEmails as $email) {
            $page->setVar([
                'INVALID_EMAIL' => '"' . Scrub::text($email) . '"'
            ]);
            $page->parse('invalid_address_block', 'invalid_address', true);
        }
        $page->parse('alert_box_block', 'alert_box', false);
    } else {
        $page->setVar('alert_box_block', '');
    }

    foreach ($addressLists['replyto'] as $replyToKey => $replyTo) {
        $addressLists['replyto'][$replyToKey] = Codec::decode($replyTo);
    }

    $output = array(
        'SUBJECT' => $_REQUEST['subject'] ? Scrub::text($_REQUEST['subject']) : '',
        'BODY'    => '',
        'REPLYTO' => json_encode($addressLists['replyto']),
        'CC'      => json_encode($addressLists['cc']),
        'BCC'     => json_encode($addressLists['bcc']),
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
