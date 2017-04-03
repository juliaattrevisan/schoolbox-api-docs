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
use Schoolbox\UserMapper;
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

    /** @var UserMapper */
    private $userMapper;


    /**
     * @param TwigEmailFactory $emailFactory
     * @param LoggerInterface  $log
     * @param UserMapper       $userMapper
     */
    public function __construct(TwigEmailFactory $emailFactory, LoggerInterface $log, UserMapper $userMapper)
    {
        $this->emailFactory = $emailFactory;
        $this->log          = $log;
        $this->userMapper = $userMapper;
    }


    /**
     * @param QueueInterface $list
     * @param User           $recipient
     * @param string         $template
     * @param string         $subject
     * @param int            $sortDir
     * @throws Swift_RfcComplianceException
     * @throws UnderflowException
     * @return bool
     */
    public function sendListContents(QueueInterface $list, User $recipient, $template, $subject = '', $sortDir = SORT_ASC)
    {
        $address = $this->getEmailAddress($recipient);

        if (empty($address)) {
            throw new Swift_RfcComplianceException("Empty address for user {$recipient->getId()}");
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

        $context = $decorator->renderAsTwigContext($this->emailFactory->getCoreContext(), 0, $sortDir);
        $context['recipient'] = $recipient->getFirstname();

        // build the email object
        $email = $this->emailFactory->build($template, $subject);
        $email->setContext($context)->setTo($address);

        // we can only include the reply-to header if there is but one email
        if ($decorator->count() === 1) {
            $email->setReplyTo($this->getEmailAddresses(
                $this->userMapper->findByColumn('id', $decorator->getNotifications(1)[0]->getEffectorIds())->getAll()
            ));
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


    /**
     * Extract email addresses for all given users
     *
     * @param User[] $users
     * @return string[]
     */
    private function getEmailAddresses(array $users)
    {
        return array_filter(
            array_map([ $this, 'getEmailAddress' ], $users)
        );

    }


    /**
     * @param User $user
     * @return string
     */
    private function getEmailAddress(User $user)
    {
        $address = trim($user->getEmail());

        if ($address) {
            // explicitly an rfc822 email spec
            return sprintf('%s <%s>', $user->formatName(), $address);
        }

        return '';
    }

}