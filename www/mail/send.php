<?php
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage;

/************************************************************
*                                                           *
*         Copyright (c) 2013 Alaress Pty Ltd                *
*                                                           *
************************************************************/
//outputs form to get users mail details and processes the information

require_once __DIR__."/../../include/Bootstrap.php";

$page = new SitePage();

function sendMail($to, $cc, $bcc, $subject, $message, $attachments = array())
{
    $mail = new Swift_Message();

    //get users email address to use in from field
    $email = new Email($_SESSION['user']->getEmail());

    if ($email->isValid()) {
        $mail->addFrom($_SESSION['user']->getEmail(), $_SESSION['user']->formatName());
        $mail->addReplyTo($_SESSION['user']->getEmail(), $_SESSION['user']->formatName());
    }
    else {
        error(42);
    }

    //if id is submitted to form convert to email
    if(is_numeric($to)) {

        $userMapper = new \Schoolbox\UserMapper();

        try {
            $toUser = $userMapper->findByID($to);
            $email = new Email($toUser->getEmail());

            if ($email->isValid()) {
                $mail->addTo($toUser->getEmail());
            } else {
                error(9, 'Your email address is not valid.');
            }

        } catch (Exception $e) {
            error(7);
        }
    }
    else {
        foreach (Mailer::parseAddressString($to) as $address => $name) {
            try {
                $mail->addTo($address, $name);
            }
            catch (Exception $e) {
                error(9, 'Email address "'.$address.'" is not valid.');
            }
        }
    }

    //check if its got no to address but has either cc or bcc
    //put the users email in the to field
    if (!stristr($to,'@') && ($bcc || $cc)) {
        try {
            $mail->addTo($_SESSION['user']->getEmail(), $to);
        }
        catch (Exception $e) {
            error(9, 'Your email address is not valid.');
        }
    }

    //fix qoute escapes
    if (get_magic_quotes_gpc())
    {
        $message = stripslashes($message);
        $subject = stripslashes($subject);
    }

    //create email header
    $mail->setSubject($subject);

    if (strrchr($cc, '@')) {
        foreach (Mailer::parseAddressString($cc) as $address => $name) {
            try {
                $mail->addCc($address, $name);
            }
            catch (Exception $e) {
                error(9, 'Email address "'.$address.'" is not valid.');
            }
        }
    }

    if (strrchr($bcc, '@')) {
        foreach (Mailer::parseAddressString($bcc) as $address => $name) {
            try {
                $mail->addBcc($address, $name);
            }
            catch (Exception $e) {
                error(9, 'Email address "'.$address.'" is not valid.');
            }
        }
    }

    $converter = new \Html2Text\Html2Text($message);
    $mail->setBody($converter->get_text(), null, 'utf-8');
    $mail->addPart($message, 'text/html', 'utf-8');

    $factory = new CollectorFactory();
    $files = $factory->build($attachments)->getAll();

    foreach ($files as $storage) {
        $attach = Swift_Attachment::newInstance($storage->read(), $storage->getBasename(), $storage->getMime());
        $attach->setFilename($storage->getBasename());
        $attach->setContentType($storage->getMime());
        $mail->attach($attach);
    }

    try {
        Swift_Mailer::newInstance(Mailer::getSwiftTransport())->send($mail);
    }
    catch (Exception $ex) {
        error(9, $ex->getMessage());
    }
}


//if form has been submitted
if(isset($_POST['to']) && $_POST['to'] && Cfg::get('mail_enable'))
{
    $attachments = isset($_POST['attachment']) ? $_POST['attachment'] : array();

    sendMail($_POST['to'], $_POST['cc'], $_POST['bcc'], $_POST['subject'], $_POST['message'], $attachments);

    $page->backToTheFuture(2);
}
else
{
    SitePage::localRedir('/mail/createMail.php');
}
