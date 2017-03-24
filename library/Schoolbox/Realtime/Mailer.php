<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime;

use Alaress\Async\Queue\QueueInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Mail\TwigEmailFactory;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\User;
use Swift_RfcComplianceException;
use Swift_SwiftException;
use Twig_Error_Runtime;
use UnderflowException;


class Mailer
{
    /** @var TwigEmailFactory */
    private $emailFactory;

    /** @var LoggerInterface */
    private $log;


    /**
     * @param TwigEmailFactory $emailFactory
     * @param LoggerInterface  $log
     */
    public function __construct(TwigEmailFactory $emailFactory, LoggerInterface $log)
    {
        $this->emailFactory = $emailFactory;
        $this->log          = $log;
    }


    /**
     * @param QueueInterface $list
     * @param User           $user
     * @param string         $template
     * @param string         $subject
     * @param int            $sortDir
     * @throws Swift_RfcComplianceException
     * @throws UnderflowException
     * @return bool
     */
    public function sendListContents(QueueInterface $list, User $user, $template, $subject = '', $sortDir = SORT_ASC)
    {
        $emailAddress = trim($user->getEmail());

        if (empty($emailAddress)) {
            throw new Swift_RfcComplianceException("Empty address for user {$user->getId()}");
        }

        $decorator = new Decorator();

        $list->fetchIntoBundle($decorator, true);

        if (!$decorator->count()) {
            throw new UnderflowException('No messages to deliver');
        }

        if (empty($subject)) {
            // if no subject given, infer from the notifications compiled
            $subject = $decorator->renderTitleAsPlaintext($decorator->getNotifications(1)[0]);
        }

        $this->log->debug(sprintf('Compiling %d notification%s for user %s', $decorator->count(), $decorator->count() === 1 ? '' : 's', $user->getUsername()));

        $context = $decorator->renderAsTwigContext($this->emailFactory->getCoreContext(), 0, $sortDir);
        $context['recipient'] = $user->getFirstname();

        // explicitly an rfc822 email spec, so it sets, not adds
        $address = sprintf('%s <%s>', $user->formatName(), $emailAddress);

        // build the email object
        $email = $this->emailFactory->build($template, $subject);
        $email->setContext($context)->setTo($address);

        try {
            // away she goes
            $sent = $email->send();

            // if email sent successfully, ACK, otherwise NACK
            if ($sent) {
                $this->log->notice("Mail sent successfully to $address, draining list");
                $list->drain();

                return true;
            }

            $this->log->warning("Failed to email to $address");

        } catch (Swift_SwiftException $e) {
            $this->log->warning(get_class($e) . ': ' . $e->getMessage(), [$e]);

        } catch (Twig_Error_Runtime $e) {
            $this->log->error(get_class($e) . ': ' . $e->getMessage(), [$e]);

            if (strpos($e->getMessage(), 'Invalid multibyte sequence in argument') !== false) {
                // this means the template couldn't render the mail because of bad data, so we have to flush it :/
                $list->drain();
                $this->log->warning('Message content could not be rendered; data lost');
            }
        }

        return false;
    }

}