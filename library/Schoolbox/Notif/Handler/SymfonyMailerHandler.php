<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Notif\Handler;

use Alaress\Mail\Mailer as SimpleEmail;
use DateTimeImmutable;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\MailHandler;
use Monolog\Logger;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Symfony mailer handler.
 *
 * A port of the Swift mailer handler.
 */
class SymfonyMailerHandler extends MailHandler
{
    /**
     * The base email to send.
     */
    private SimpleEmail $email;

    /**
     * Constructor
     *
     * @param SimpleEmail $email
     * @param int         $level
     * @param bool        $bubble
     */
    public function __construct(SimpleEmail $email, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
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
            $email->setSubject($subjectFormatter->format($this->getHighestRecord($records)));
        }

        if ($this->isHtmlBody($content)) {
            $email->setHtml($content);
        } else {
            $email->setText($content);
        }
        $email->setDate(new DateTimeImmutable());

        $email->send();
    }
}
