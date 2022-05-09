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
use Schoolbox\Config\Loader as ConfigLoader;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$config = ConfigLoader::getInstance();
/** @var \Schoolbox\User $currentUser */
$user = $_SESSION['user'];

$groupHandler = new Group();
$externalHandler = new External_User();
$userMapper = UserMapper::getInstance();

if ($config->get('mail_enable') == 1) {
    // Parse out list of emails passed
    // The name needs to be included in both the ID and value
    $invalidEmails = [];

    /* The replyto array key must come first, because we use it to dedupe replyto
     * senders against cc and bcc fields, to prevent them self-spamming unnecessarily
     */
    $addressLists = [
        'replyto' => [],
        'cc' => [],
        'bcc' => []
    ];

    // Only students' externalIds are passed in. Building emails from there.
    $buildEmailFromId = isset($_POST['selectedIds']) && !empty($_POST['selectedIds']);

    if ($buildEmailFromId) {
        $studentExternalIds = explode(',', $_POST['selectedIds']);
        $selectedGroups = isset($_POST['selectedGroups'])
            ? (is_array($_POST['selectedGroups'])
                ? $_POST['selectedGroups']
                : explode(',', $_POST['selectedGroups'])
            )
            : [];
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
        if (in_array('tutors', $selectedGroups)) {
            $addressLists['bcc'] = array_merge($addressLists['bcc'], $externalHandler->getTutorEmails($studentExternalIds));
        }
    }

    /* A list of email addresses that appeared in the To field
     */
    $replyToEmailAddresses = [];

    foreach ($addressLists as $type => $request) {
        if (null !== ($_REQUEST[$type] ?? null)) {
            $emailAddresses = Mailer::parseAddressString($_REQUEST[$type]);

            /* Remember some invalid emails, if any were found
             */
            $invalidEmails[] = $emailAddresses[AbstractMailer::KEY_INVALID];

            /* Remember a list of email addresses that were in the To field
             * There's a sequential dependency here, such that replyto must be processed
             * before either the cc or bcc structs, as defined by $addressLists initialisation
             */
            if ('replyto' === $type) {
                $replyToEmailAddresses = array_keys($emailAddresses[AbstractMailer::KEY_VALID]);
            }

            if (!empty($emailAddresses[AbstractMailer::KEY_VALID])) {
                foreach ($emailAddresses[AbstractMailer::KEY_VALID] as $email => $name) {
                    /* For the CC and BCC fields, if an email address was already in To field,
                     * disregard it because CC'ing and BCC'ing to yourself is extreme when assigned
                     * by the system rather than the human
                     */
                    if ('replyto' === $type || !in_array($email, $replyToEmailAddresses, true)) {
                        $addressLists[$type][] = Mailer::buildAddress($email, $name);
                    }
                }
            }
        } elseif (!$buildEmailFromId && null === ($_REQUEST[$type] ?? null) && $type === 'bcc') {
            /* For the CC and BCC fields, if an email address was already in To field,
             * disregard it because CC'ing and BCC'ing to yourself is extreme when assigned
             * by the system rather than the human
             */
            if (!in_array($_SESSION['user']->getEmail(), $replyToEmailAddresses, true)) {
                $addressLists[$type][] = Mailer::buildAddress(
                    $_SESSION['user']->getEmail(),
                    $_SESSION['user']->getFullName()
                );
            }
        }
    }

    /**
     * build the email signature if it is defined, and the user has a schoolbox email
     * matching on user's email domain matches the smtp from address
     */
    $emailBody = '';
    $signature = $config->get('email_signature');
    $tokenReplacer = new \Schoolbox\TokenReplacer\Simple();
    if (
        $config->get('mail_address') === explode('@', $user->getEmail())[1] &&
        null !== $signature
    ) {
        $emailBody = "<br><br>---<br>" . $tokenReplacer->replace(
            $signature,
            [
                '[title]'     => $user->getTitle(),
                '[firstname]' => $user->getFirstname(),
                '[lastname]'  => $user->getLastname(),
                '[email]'     => $user->getEmail(),
                '[position]'  => $user->getPositionTitle(),
            ]
        );
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
        'SUBJECT' => ($_REQUEST['subject'] ?? false) ? Scrub::text($_REQUEST['subject']) : '',
        'BODY'    => $emailBody,
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

    $uri = (new \Schoolbox\Url\Factory($config))->buildFullyQualified()->setScheme("https");
    $urlOrigin = array( 'BASE_HREF' => $uri );
    $page->setVar($urlOrigin);

    $page->outputPage();
} else {
    error(7);
}
