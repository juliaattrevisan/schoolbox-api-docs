<?php

/**
 * Outputs form to get users mail details and processes the information
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Mail\AbstractMailer;
use League\Flysystem\FileNotFoundException;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email as MimeEmail;

require_once __DIR__ . "/../../conf/core/require.php";
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

function sendMail(
    $to,
    $cc,
    $bcc,
    $subject,
    $message,
    $attachments,
    FilesystemProxy $filesystemProxy
) {
    $mail = new MimeEmail();

    //get users email address to use in from field
    $email = new Email($_SESSION['user']->getEmail());

    if ($email->isValid()) {
        $from = new Address(
            $_SESSION['user']->getEmail(),
            $_SESSION['user']->formatName() ?? '',
        );
        $mail->addFrom($from);
        $mail->addReplyTo($from);
    } else {
        error(42);
    }

    //if id is submitted to form convert to email
    if (is_numeric($to)) {
        $userMapper = new \Schoolbox\UserMapper();

        try {
            $toUser = $userMapper->findById($to);
            $email = new Email($toUser->getEmail());

            if ($email->isValid()) {
                $mail->addTo(new Address($toUser->getEmail()));
            } else {
                error(9, 'Your email address is not valid.');
            }
        } catch (Exception $e) {
            error(7);
        }
    } else {
        $emailAddresses = Mailer::parseAddressString(Mailer::standardiseEmailSeparator($to));

        foreach ($emailAddresses[AbstractMailer::KEY_VALID] as $address => $name) {
            try {
                $mail->addTo(new Address($address, $name ?? ''));
            } catch (Exception $e) {
                error(9, 'Email address "' . $address . '" is not valid.');
            }
        }
    }

    //check if its got no to address but has either cc or bcc
    //put the users email in the to field
    if (!stristr($to, '@') && ($bcc || $cc)) {
        try {
            $mail->addTo(new Address($_SESSION['user']->getEmail(), $to ?? ''));
        } catch (Exception $e) {
            error(9, 'Your email address is not valid.');
        }
    }

    //create email header
    $mail->subject($subject);

    if (strrchr($cc, '@')) {
        $emailAddresses = Mailer::parseAddressString(Mailer::standardiseEmailSeparator($cc));

        foreach ($emailAddresses[AbstractMailer::KEY_VALID] as $address => $name) {
            try {
                $mail->addCc(new Address($address, $name ?? ''));
            } catch (Exception $e) {
                error(9, 'Email address "' . $address . '" is not valid.');
            }
        }
    }

    if (strrchr($bcc, '@')) {
        $emailAddresses = Mailer::parseAddressString(Mailer::standardiseEmailSeparator($bcc));

        foreach ($emailAddresses[AbstractMailer::KEY_VALID] as $address => $name) {
            try {
                $mail->addBcc(new Address($address, $name ?? ''));
            } catch (Exception $e) {
                error(9, 'Email address "' . $address . '" is not valid.');
            }
        }
    }

    $converter = new Alaress\Html\ToText($message);
    $mail->text($converter->getText());
    $mail->html($message);

    try {
        $factory = new CollectorFactory();
        $files = $factory->build($attachments)->getAll();

        foreach ($files as $storage) {
            $mail->attach($filesystemProxy->readStream($storage), $storage->getBasename(), $storage->getMime());
        }
    } catch (\Schoolbox\Mapper\Exception | FileNotFoundException $e) {
        error(41);
        exit; // shut UP, storm.
    } catch (\InvalidArgumentException $e) {
        error(43);
        exit;
    }

    try {
        $mailer = new SymfonyMailer(Mailer::getTransport());
        $mailer->send($mail);
    } catch (TransportExceptionInterface $ex) {
        error(9, $ex->getMessage());
    }
}

// As long as there is a recipient in at least one of these fields,
// we can send the email
$hasRecipient = (isset($_POST['to']) && $_POST['to']) || (isset($_POST['bcc']) && $_POST['bcc']) || (isset($_POST['cc']) && $_POST['cc']);

//if form has been submitted
if ($hasRecipient && Config::get('mail_enable')) {
    $attachments = isset($_POST['attachment']) ? $_POST['attachment'] : array();

    sendMail(
        $_POST['to'],
        $_POST['cc'],
        $_POST['bcc'],
        $_POST['subject'],
        $_POST['message'],
        $attachments,
        $kernel->get('filesystem.storageProxy'),
    );

    $page->backToTheFuture(3);
} else {
    error(ErrorMapper::ERROR_INFORMATION_MISSING);
}
