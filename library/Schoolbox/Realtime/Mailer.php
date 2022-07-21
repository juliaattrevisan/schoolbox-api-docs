<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime;

use Alaress\Async\Queue\QueueInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Collection;
use Schoolbox\Mail\EmailFactory;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Twig\Error\RuntimeError;
use UnderflowException;

class Mailer
{
    /** @var EmailFactory */
    private $emailFactory;

    /** @var LoggerInterface */
    private $log;

    /** @var UserMapper */
    private $userMapper;

    /** @var bool */
    private $useReplyTo;


    /**
     * @param EmailFactory    $emailFactory
     * @param LoggerInterface $log
     * @param UserMapper      $userMapper
     * @param bool            $includeReplyToHeaders
     */
    public function __construct(EmailFactory $emailFactory, LoggerInterface $log, UserMapper $userMapper, $includeReplyToHeaders)
    {
        $this->emailFactory = $emailFactory;
        $this->log          = $log;
        $this->userMapper   = $userMapper;
        $this->useReplyTo   = $includeReplyToHeaders;
    }


    /**
     * @param QueueInterface $list
     * @param User           $recipient
     * @param string         $template
     * @param string         $subject
     * @param int            $sortDir
     * @return bool
     * @throws UnderflowException
     * @throws RfcComplianceException
     */
    public function sendListContents(QueueInterface $list, User $recipient, string $template, string $subject = '', int $sortDir = SORT_ASC): bool
    {
        $address = trim($recipient->getEmail());

        if (empty($address)) {
            throw new RfcComplianceException("Empty address for user {$recipient->getId()}");
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

        $this->log->debug(sprintf('Compiling %d notification%s for user %s', $decorator->count(), $decorator->count() === 1 ? '' : 's', $recipient->getUsername()));

        // build the email object
        $email = $this->emailFactory->buildTwigEmail($template, $subject, new Address($address, $recipient->formatName()));
        $email->setLogger($this->log);
        $context              = $decorator->renderAsTwigContext($email->getCoreContext(), 0, $sortDir);
        $context['recipient'] = $recipient->getFirstname();
        $email->setContext($context);

        // we can only include the reply-to header if there is but one email
        if ($this->useReplyTo && $decorator->count() === 1) {
            $effectors = $this->userMapper->findByColumn('id', $decorator->getNotifications(1)[0]->getReplyToIds());
            foreach ($effectors as $effector) {
                try {
                    $email->addReplyTo(new Address($effector->getEmail(), $effector->formatName()));
                } catch (RfcComplianceException | LogicException $e) {
                    $this->log->warning('Error applying reply-to: ' . $e->getMessage());
                }
            }
        }

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
        } catch (TransportExceptionInterface $e) {
            $this->log->warning(get_class($e) . ': ' . $e->getMessage(), ['exception' => $e]);
        } catch (RuntimeError $e) {
            $this->log->error(get_class($e) . ': ' . $e->getMessage(), ['exception' => $e]);

            if (strpos($e->getMessage(), 'Invalid multibyte sequence in argument') !== false) {
                // this means the template couldn't render the mail because of bad data, so we have to flush it :/
                $list->drain();
                $this->log->warning('Message content could not be rendered; data lost');
            }
        }

        return false;
    }
}
