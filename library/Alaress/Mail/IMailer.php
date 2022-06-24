<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Mail;

use Schoolbox\IConfigLoader;
use Psr\Log\LoggerInterface;
use Schoolbox\User;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;

interface IMailer
{

    /**
     * @param IConfigLoader $conf
     * @param bool $reinitialise
     * @return TransportInterface
     */
    public static function getConfiguredTransport($conf, $reinitialise = false);

    /**
     * @param bool $reinitialise
     * @return TransportInterface
     */
    public static function getTransport($reinitialise = false);

    /**
     * @param string $emailAddresses
     *
     * @return array ['valid' => [['email' => 'name']], 'invalid' => [['email' => 'name']]]
     */
    public static function parseAddressString(string $emailAddresses): array;

    /**
     * @param string $address
     * @param string $name
     * @return string
     */
    public static function buildAddress($address, $name = '');

    public function __construct(string $template, string $subject, string|array|Address|User $to);

    public function setFrom(string $email, string $name = '');

    public function setReplyTo($email, string $name = '');

    public function setFromAsCurrentUser();

    public function setTo(string|array|Address|User $to);

    public function setSubject($subject);

    public function addFile($file, $mime = 'text/plain', $filename = '', $isFile = true);

    public function addImage($file, $type = 'application/octet');

    public function send($to = '');

    public function setLogger(LoggerInterface $logger);

    public function encodeBase64();
}
