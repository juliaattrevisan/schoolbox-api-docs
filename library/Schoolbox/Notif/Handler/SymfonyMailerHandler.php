<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Notif\Handler;

use DateTimeImmutable;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\MailHandler;
use Monolog\Logger;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Symfony mailer handler.
 *
 * A port of the Swift mailer handler.
 */
class SymfonyMailerHandler extends MailHandler
{
    /**
     * The Symfony mailer instance.
     */
    private MailerInterface $mailer;

    /**
     * The base email to send.
     */
    private Email $email;

    /**
     * Constructor
     *
     * @param MailerInterface $mailer
     * @param Email           $email
     * @param int             $level
     * @param bool            $bubble
     */
    public function __construct(MailerInterface $mailer, Email $email, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->mailer = $mailer;
        $this->email  = $email;
    }

    /**
     * Send the email.
     *
     * @param string $content
     * @param array  $records
     * @throws TransportExceptionInterface
     */
    protected function send(string $content, array $records): void
    {
        $email = clone $this->email;

        if ($records) {
            $subjectFormatter = new LineFormatter($email->getSubject());
            $email->subject($subjectFormatter->format($this->getHighestRecord($records)));
        }

        if ($this->isHtmlBody($content)) {
            $email->html($content);
        } else {
            $email->text($content);
        }
        $email->date(new DateTimeImmutable());

        $this->mailer->send($email);
    }
}
