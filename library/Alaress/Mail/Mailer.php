<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Mail;

use Alaress\Exception\IO\IOException;
use Alaress\Lumberjack\Logger;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\EmailDKIM\Mapper;
use Schoolbox\Admin\EmailDKIM\Model as EmailDKIM;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimOptions;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\TextPart;

class Mailer
{
    private const MAX_RETRIES   = 4;
    private const RETRY_TIMEOUT = 1;

    /**
     * Instance of mail transporter.
     */
    protected static TransportInterface $transport;

    protected Email $email;

    /**
     * List of headers that are added to the email that is sent.
     */
    protected array $headers = [];

    protected LoggerInterface $log;
    protected IConfigLoader $config;
    protected Mapper $dkimMapper;

    /**
     * Whether to use base64 rather than quoted printable
     */
    private bool $encodeBase64 = false;

    /**
     * System default from address
     */
    private Address $systemFrom;

    /**
     * Construct the basics of a new Mailer
     *
     * @param string                    $subject
     * @param Address|User|null $to
     * @param IConfigLoader             $config
     * @param Mapper                    $dkimMapper
     * @throws RfcComplianceException
     */
    public function __construct(string $subject, Address | User | null $to, IConfigLoader $config, Mapper $dkimMapper)
    {
        $this->config     = $config;
        $this->dkimMapper = $dkimMapper;

        $this->email = new Email();

        // setup default from
        $this->systemFrom = new Address($this->config->get(ConfigMapper::NAME_MAIL_FROM_ADDRESS), $this->config->get(ConfigMapper::NAME_MAIL_FROM_NAME));
        $this->setFrom($this->systemFrom);
        $this->setTo($to);
        $this->setSubject($subject);

        // default logger does nothing
        $this->log = new Logger(null);
    }


    /**
     * Provide a log interface to send mail sending errors to
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->log = $logger;
        return $this;
    }


    /**
     * Set To
     * @param Address | User | null $to
     * @throws RfcComplianceException
     * @return void
     */
    public function setTo(Address | User | null $to): void
    {
        if ($to instanceof User) {
            if (!$to->getEmail()) {
                throw new RfcComplianceException("User {$to->getId()} doesn't have email address");
            }
            $this->email->addTo(new Address($to->getEmail(), $to->formatName()));
        } elseif ($to instanceof Address) {
            $this->email->addTo($to);
        }
    }

    /**
     * Set CC
     * @param Address $cc
     */
    public function setCc(Address $cc): void
    {
        $this->email->addCc($cc);
    }

    /**
     * Set BCC
     * @param Address $bcc
     */
    public function setBcc(Address $bcc): void
    {
        $this->email->addBcc($bcc);
    }

    /**
     * Set subject
     * @param string $subject
     */
    public function setSubject(string $subject): void
    {
        $this->email->subject($subject);
    }

    /**
     * Get subject
     * @return string|null
     */
    public function getSubject(): ?string
    {
        return $this->email->getSubject();
    }

    /**
     * Set text
     * @param string $text
     */
    public function setText(string $text): void
    {
        $this->email->text($text);
    }

    /**
     * Set html
     * @param string $html
     */
    public function setHtml(string $html): void
    {
        $this->email->html($html);
    }

    /**
     * Set from
     * @param Address $address
     */
    public function setFrom(Address $address): void
    {
        $this->email->from($address);
        $this->email->returnPath($address->getEncodedAddress()); // The name should not be passed in return path so we only set the email address in return path
    }

    /**
     * Set the reply-to header
     * @param Address $address
     */
    public function addReplyTo(Address $address): void
    {
        $this->email->addReplyTo($address);
    }

    /**
     * Set date
     * @param \DateTimeInterface $date
     */
    public function setDate(\DateTimeInterface $date): void
    {
        $this->email->date($date);
    }

    /**
     * Add file
     * @param mixed       $file
     * @param string      $type
     * @param string|null $filename
     * @param bool        $isFile
     */
    public function addFile($file, $type = 'text/plain', $filename = '', $isFile = true): void
    {
        // add attachment to email
        if ($isFile) {
            $this->email->attachFromPath($file, $filename, $type);
        } else {
            $this->email->attach($file, $filename, $type);
        }
    }

    /**
     * Add image
     * @param mixed       $file
     * @param string|null $type
     * @return Email
     */
    public function addImage($file, $type = 'application/octet'): Email
    {
        //add inline image to email
        // Returns a string so its source can be used later
        return $this->email->embed($file, null, $type);
    }


    protected function composeHTML(): string
    {
        return (string)$this->email->getHtmlBody();
    }


    protected function composePlain(): string
    {
        return (string)$this->email->getTextBody();
    }

    /**
     * Encodes email in base64 rather than quoted printable.
     */
    public function encodeBase64(): void
    {
        $this->encodeBase64 = true;
    }

    /**
     * Encode with base64, if specified
     * @return void
     */
    private function encode(): void
    {
        if (!$this->encodeBase64) {
            return;
        }
        $this->email->setBody(
            new AlternativePart(
                new TextPart(
                    $this->email->getTextBody(),
                    $this->email->getTextCharset(),
                    'plain',
                    'base64'
                ),
                new TextPart(
                    $this->email->getHtmlBody(),
                    $this->email->getHtmlCharset(),
                    'html',
                    'base64'
                )
            )
        );
    }

    /**
     * Attempts to send the email constructed by this class. Returns mailing success or failure.
     *
     * @return bool
     * @throws TransportExceptionInterface
     */
    public function send(): bool
    {
        // construct body and set headers
        $this->email->text($this->composePlain());
        $this->email->html($this->composeHTML());
        $this->encode();
        foreach ($this->headers as $name => $value) {
            $this->email->getHeaders()->addTextHeader($name, $value);
        }

        try {
            return $this->trySend(self::MAX_RETRIES);
        } catch (IOException $e) {
            return $this->trySend();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException("Failed to send email", $e->getCode(), $e);
        }
    }


    /**
     * Sends a message nowhere.
     *
     * This is intended for use in time-sensitive requests, where it should not
     * be possible to infer from the request time whether an email was sent.
     */
    public function sendToBlackhole(): void
    {
        // This has to be sent somewhere
        $this->email->to(new Address('devnull@blackhole'));

        // construct body and set headers
        $this->email->text($this->composePlain());
        $this->email->html($this->composeHTML());
        foreach ($this->headers as $name => $value) {
            $this->email->getHeaders()->addTextHeader($name, $value);
        }
        $mailer = new NullTransport();
        try {
            $mailer->send($this->email);
        } catch (TransportExceptionInterface $e) {
            // noop: who cares, it's blackholed
        }
    }


    /**
     * @param int  $retries
     * @param bool $reinitialise
     * @return bool
     * @throws TransportExceptionInterface if not send after `$retries` attempts
     */
    private function trySend($retries = 0, $reinitialise = false): bool
    {
        // create transport and send email
        try {

            // Check if the SES config exists, if yes, use SES transporter
            $configKeys = ['amazon_ses_username', 'amazon_ses_password', 'amazon_ses_region'];
            $configValues = array_map(fn($key) => $this->config->get($key), $configKeys);

            if (array_filter($configValues)) {
                [$username, $password, $region] = $configValues;
                $endpoint = sprintf('email-smtp.%s.amazonaws.com', $region);

                $transport = new EsmtpTransport($endpoint, 587);
                $transport->setUsername($username);
                $transport->setPassword($password);
                $transport->setLocalDomain($endpoint);

                $mailer = new SymfonyMailer($transport);
            } else {
                $mailer = new SymfonyMailer(self::getConfiguredTransport($this->config, $reinitialise));
            }

            $this->configureMailer($mailer);

            // get all recipients before signing
            $recipients = array_merge($this->email->getTo(), $this->email->getCC(), $this->email->getBcc());

            if ($this->config->get(ConfigMapper::NAME_MAIL_FORCE_FROM_SYSTEM)) {
                $this->forceFrom();
            }

            foreach ($this->email->getFrom() as $from) {
                [$email, $domain] = explode('@', $from->getAddress());
                try {
                    $dkim    = $this->dkimMapper->findOneByColumn('domain', $domain);
                    $message = $this->getSignedMessage($dkim->getPrivate(), $dkim->getDomain());
                } catch (Exception $e) {
                    $message = $this->email;
                }
                $mailer->send($message, new Envelope($from, $recipients));
            }
        } catch (TransportExceptionInterface $e) {
            $retriesMsg = $retries == 1 ? 'retry remains' : 'retries remain';
            // this means "mail server went away"
            $errMsg = sprintf('Internal Mailing %s occurred (%d %s): %s', \get_class($e), $retries, $retriesMsg, $e->getMessage());

            $this->log->error($errMsg, ['exception' => $e]);

            if ($retries <= 0) {
                throw $e;
            }

            sleep(self::RETRY_TIMEOUT);

            return $this->trySend($retries - 1, true);
        }

        return true;
    }


    /**
     * Configure the given Swift_Mailer object prior to sending the email
     *
     * @param MailerInterface $mailer
     */
    protected function configureMailer($mailer)
    {
        // noop - to be overridden
    }


    /**
     * @param IConfigLoader $conf
     * @param bool          $reinitialise
     * @return TransportInterface
     */
    public static function getConfiguredTransport($conf, $reinitialise = false)
    {
        if (!(isset(self::$transport)) || $reinitialise) {
            if ($conf->get('mail_smtp_host') && $conf->get('mail_smtp_port')) {
                self::$transport = new EsmtpTransport(
                    $conf->get('mail_smtp_host'),
                    $conf->get('mail_smtp_port')
                );
                self::$transport->setLocalDomain(
                    $conf->get('site_hostname') . '.' . $conf->get('site_domain')
                );

                //Set auth details if available
                if ($conf->get('mail_smtp_username') && $conf->get('mail_smtp_password')) {
                    self::$transport->setUsername($conf->get('mail_smtp_username'));
                    self::$transport->setPassword($conf->get('mail_smtp_password'));
                }
            } else {
                self::$transport = new NullTransport();
            }
        }
        return self::$transport;
    }

    /**
     * Sign - note: bcc will be dropped by signing
     *        https://github.com/symfony/symfony/issues/44006
     * @param string $privateKey
     * @param string $domain
     * @return Message
     */
    private function getSignedMessage(string $privateKey, string $domain): Message
    {
        $signer       = new DkimSigner($privateKey, $domain, EmailDKIM::SELECTOR);
        $emailForSign = new Message($this->email->getPreparedHeaders(), $this->email->getBody());
        return $signer->sign(
            $emailForSign,
            (new DkimOptions())
                ->bodyCanon('relaxed')
                ->headerCanon('relaxed')
                ->headersToIgnore(['message-id', 'date', 'mime-version'])
                ->toArray()
        );
    }

    /**
     * Replace current from address with system from address
     * @return void
     */
    private function forceFrom()
    {
        /** @var Address[] $replaceFrom */
        $replaceFrom = [];
        foreach ($this->email->getFrom() as $from) {
            // if current from is not system from, move it to reply to
            if (
                $from->getAddress() !== $this->systemFrom->getAddress()
                && empty($this->email->getReplyTo())
            ) {
                $this->email->addReplyTo($from);
            }
            $replaceFrom[] = new Address($this->systemFrom->getAddress(), $this->systemFrom->getName());
        }
        foreach ($replaceFrom as $i => $replace) {
            if ($i === 0) {
                $this->email->from($replace);
            } else {
                $this->email->addFrom($replace);
            }
        }
    }
}
