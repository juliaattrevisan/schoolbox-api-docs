<?php
/**
 * Outputs form to get users mail details and processes the information
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Mail\AbstractMailer;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Alaress\Mail\AttachmentStream;
use Schoolbox\Error\Mapper as ErrorMapper;

require_once __DIR__ . "/../../conf/core/require.php";
$kernel = new \Schoolbox\Application\WebRequest\Kernel();


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
        $emailAddresses = Mailer::parseAddressString(Mailer::standardiseEmailSeparator($to));

        foreach ($emailAddresses[AbstractMailer::KEY_VALID] as $address => $name) {
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

    //fix quote escapes
    if (get_magic_quotes_gpc())
    {
        $message = stripslashes($message);
        $subject = stripslashes($subject);
    }

    //create email header
    $mail->setSubject($subject);

    if (strrchr($cc, '@')) {
        $emailAddresses = Mailer::parseAddressString(Mailer::standardiseEmailSeparator($cc));

        foreach ($emailAddresses[AbstractMailer::KEY_VALID] as $address => $name) {
            try {
                $mail->addCc($address, $name);
            }
            catch (Exception $e) {
                error(9, 'Email address "'.$address.'" is not valid.');
            }
        }
    }

    if (strrchr($bcc, '@')) {
        $emailAddresses = Mailer::parseAddressString(Mailer::standardiseEmailSeparator($bcc));

        foreach ($emailAddresses[AbstractMailer::KEY_VALID] as $address => $name) {
            try {
                $mail->addBcc($address, $name);
            }
            catch (Exception $e) {
                error(9, 'Email address "'.$address.'" is not valid.');
            }
        }
    }

    $converter = new Alaress\Html\ToText($message);
    $mail->setBody($converter->getText(), null, 'utf-8');
    $mail->addPart($message, 'text/html', 'utf-8');

    $basename = '';
    try {
        $factory = new CollectorFactory();
        $files = $factory->build($attachments)->getAll();

        foreach ($files as $storage) {
            $stream = new AttachmentStream($storage->readStream());
            $basename = $storage->getBasename();
            $attach = new Swift_Attachment($stream, $basename, $storage->getMime());
            $mail->attach($attach);
        }
    } catch (Swift_IoException $e) {
        error(9, $e->getMessage().': '.$basename);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(41);
        exit; // shut UP, storm.
    } catch (\InvalidArgumentException $e) {
        error(43);
        exit;
    }

    try {
        $mailer = new Swift_Mailer(Mailer::getSwiftTransport());
        $mailer->send($mail);
    }
    catch (Exception $ex) {
        error(9, $ex->getMessage());
    }
}

// As long as there is a recipient in at least one of these fields,
// we can send the email
$hasRecipient = (isset($_POST['to']) && $_POST['to']) || (isset($_POST['bcc']) && $_POST['bcc']) || (isset($_POST['cc']) && $_POST['cc']);

//if form has been submitted
if($hasRecipient && Config::get('mail_enable'))
{
    $attachments = isset($_POST['attachment']) ? $_POST['attachment'] : array();

    sendMail($_POST['to'], $_POST['cc'], $_POST['bcc'], $_POST['subject'], $_POST['message'], $attachments);

    $page->backToTheFuture(3);
}
else
{
    error(ErrorMapper::ERROR_INFORMATION_MISSING);
}
