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
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\MailerInterface;
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

use function count;
use function trim;

class Mailer implements IMailer
{
    // These two const are just a key for an array structure that will separate valid and invalid email addresses
    // As of the moment, these keys are being used here and in createMail.php::45 only
    public const KEY_INVALID = 'invalid';
    public const KEY_VALID   = 'valid';

    private const MAX_RETRIES   = 4;
    private const RETRY_TIMEOUT = 1;

    public const EMAIL_SEPARATOR_CODE = 0x1E;

    /**
     * Instance of mail transporter.
     *
     * @var TransportInterface
     */
    protected static $transport;

    /** @var Email */
    protected Email $email;

    /**
     * List of headers that are added to the email that is sent.
     *
     * @var array
     */
    protected array $headers;

    /** @var LoggerInterface */
    protected LoggerInterface $log;

    protected IConfigLoader $config;

    protected Mapper $dkimMapper;

    /**
     * Whether to use base64 rather than quoted printable
     */
    private bool $encodeBase64 = false;

    /**
     * Construct the basics of a new Mailer
     *
     * @param string                    $subject
     * @param string|array|Address|User $to
     * @param IConfigLoader             $config
     * @param Mapper                    $dkimMapper
     */
    public function __construct(string $subject, string | array | Address | User $to, IConfigLoader $config, Mapper $dkimMapper)
    {
        $this->config     = $config;
        $this->dkimMapper = $dkimMapper;

        $this->email = new Email();
        $this->setFrom($this->config->get('mail_from_address'), $this->config->get('mail_from_name'));

        //setup header
        $this->headers['MessageID'] = '<' . md5(time()) . '@alaressmailer>';

        $this->setTo($to);
        $this->setSubject($subject);

        // default logger does nothing
        $this->log = new Logger(null);
    }


    /**
     * @param LoggerInterface $logger
     * @return static
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->log = $logger;

        return $this;
    }


    /**
     * Set To
     * @param string|array|Address|User $to
     * @return void
     */
    public function setTo(string | array | Address | User $to)
    {
        if ($to instanceof User) {
            $this->email->addTo(new Address($to->getEmail(), $to->formatName()));
        } elseif ($to instanceof Address) {
            $this->email->addTo($to);
        } elseif (\is_array($to)) {
            if ($to['email']) {
                //Assume one dimensional array
                $this->email->addTo(new Address($to['email'], $to['name'] ?? ''));
            } else {
                if ($to['name']) {
                    // Name only, can't send email
                    throw new RfcComplianceException("No email address supplied.");
                } else {
                    // Assume two dimensional array
                    foreach ($to as $email) {
                        $this->email->addTo(new Address($email['email'], $email['name'] ?? ''));
                    }
                }
            }
        } else {
            $defaultHost = trim($this->config->get('site_hostname')) . '.' . trim($this->config->get('site_domain'));
            foreach (self::parseAddressString($to, $defaultHost)[self::KEY_VALID] as $address => $name) {
                $this->email->addTo(new Address($address, $name ?? ''));
            }
        }
    }

    /**
     * Set CC
     * @param Address $cc
     * @return void
     */
    public function setCc(Address $cc)
    {
        $this->email->addCc($cc);
    }

    /**
     * Set BCC
     * @param Address $bcc
     * @return void
     */
    public function setBcc(Address $bcc)
    {
        $this->email->addBcc($bcc);
    }

    /**
     * Set subject
     * @param string $subject
     * @return void
     */
    public function setSubject(string $subject)
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
     * @return void
     */
    public function setText(string $text)
    {
        $this->email->text($text);
    }

    /**
     * Set html
     * @param string $html
     * @return void
     */
    public function setHtml(string $html)
    {
        $this->email->html($html);
    }

    /**
     * Set from
     * @param string $email
     * @param string $name
     */
    public function setFrom(string $email, string $name = '')
    {
        $this->email->from(new Address($email, $name));
        $this->email->returnPath($email);
    }

    /**
     * Set the reply-to header
     *
     * @param string|array $email
     * @param string       $name
     */
    public function setReplyTo($email, string $name = '')
    {
        /**
         * if $email is an array... do magic combining, else just do what it was doing...
         */
        if (is_array($email)) {
            foreach ($email as $address => $name) {
                $this->email->addReplyTo(new Address($address, $name));
            }
        } else {
            $this->email->replyTo(new Address($email, $name));
        }
    }

    /**
     * Set date
     * @param \DateTimeInterface $date
     * @return void
     */
    public function setDate(\DateTimeInterface $date)
    {
        $this->email->date($date);
    }

    /**
     * Add file
     * @param mixed       $file
     * @param string      $type
     * @param string|null $filename
     * @param bool        $isFile
     * @return void
     */
    public function addFile($file, $type = 'text/plain', $filename = '', $isFile = true)
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
    public function addImage($file, $type = 'application/octet')
    {
        //add inline image to email
        // Returns a string so its source can be used later
        return $this->email->embed($file, null, $type);
    }

    /**
     * @return string
     */
    protected function composeHTML()
    {
        return (string)$this->email->getHtmlBody();
    }

    /**
     * @return string
     */
    protected function composePlain()
    {
        return (string)$this->email->getTextBody();
    }

    /**
     * Encodes email in base64 rather than quoted printable.
     */
    public function encodeBase64()
    {
        $this->encodeBase64 = true;
    }

    /**
     * Encode with base64, if specified
     * @return void
     */
    private function encode()
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
     * @param string $to
     * @return bool
     * @throws TransportExceptionInterface
     */
    public function send($to = '')
    {
        if (!empty($to)) {
            $this->setTo($to);
        }

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
    public function sendToBlackhole()
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
    private function trySend($retries = 0, $reinitialise = false)
    {
        // create transport and send email
        try {
            $mailer = new SymfonyMailer(self::getConfiguredTransport($this->config, $reinitialise));
            $this->configureMailer($mailer);

            // get all recipients before signing
            $recipients = array_merge($this->email->getTo(), $this->email->getCC(), $this->email->getBcc());

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
        if (!(self::$transport instanceof TransportInterface) || $reinitialise) {
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
     * @param string $emailAddresses A collection of email addresses in a string format that's separated with self::EMAIL_SEPARATOR_CODE
     *
     * @return array
     */
    public static function parseAddressString(string $emailAddresses, string $defaultHost): array
    {
        $return_list = [
            self::KEY_VALID   => [],
            self::KEY_INVALID => [],
        ];

        if (trim($emailAddresses) === '') {
            return $return_list;
        }
        $address_list = self::parseAddressList($emailAddresses, $defaultHost);

        foreach ($address_list[self::KEY_VALID] as $address) {
            $to                                = $address->mailbox . '@' . $address->host;
            $alias                             = property_exists($address, 'personal') ? $address->personal : null;
            $return_list[self::KEY_VALID][$to] = $alias;
        }

        if (count($address_list[self::KEY_INVALID]) > 0) {
            $return_list[self::KEY_INVALID] = $address_list[self::KEY_INVALID];
        }

        return $return_list;
    }

    /**
     * @param string $address
     * @param string $name
     * @return string
     */
    public static function buildAddress($address, $name = '')
    {
        [$mailbox, $host] = explode('@', $address, 2);

        return imap_rfc822_write_address($mailbox, $host, $name);
    }

    /**
     * @param string $emailAddresses
     * @return array
     */
    private static function parseAddressList(string $emailAddresses, string $defaultHost): array
    {
        $emails = [];
        //Explode emails by the ascii's Record Separator
        $emailList = explode(\chr(self::EMAIL_SEPARATOR_CODE), $emailAddresses);

        foreach ($emailList as $email) {
            $email = trim($email);

            // Check if string is a valid email OR
            // Check if the email within a string is valid e.g: "9AH French (FREA1)" <alaress@schoolbox.com.au>
            if (filter_var($email, FILTER_VALIDATE_EMAIL) || self::emailFromStringIsValid($email)) {
                $emails[self::KEY_VALID][] = $email;
                continue;
            }

            // If email address from username in the profile page is unchecked, and alternate email address is empty,
            // just skip those rather than showing "9AH French (FREA1)" <> as an error.
            if (self::getEmailFromString($email) !== '') {
                $emails[self::KEY_INVALID][] = $email;
            }
        }

        return [
            self::KEY_VALID   => isset($emails[self::KEY_VALID]) ? self::parseValidEmailAddresses($emails[self::KEY_VALID], $defaultHost) : [],
            self::KEY_INVALID => $emails[self::KEY_INVALID] ?? [],
        ];
    }

    /**
     * Parse valid email addresses from a string
     *
     * @param array $emails
     * @return array
     */
    private static function parseValidEmailAddresses(array $emails, string $defaultHost): array
    {
        $emailAddresses = [];
        foreach ($emails as $email) {
            $emailAddresses[] = Codec::encode($email);
        }

        return imap_rfc822_parse_adrlist(implode(',', $emailAddresses), $defaultHost);
    }

    /**
     * Get an email from a string. E.g: "9AH French (FREA1)" <alaress@schoolbox.com.au>
     * @param string $string
     * @return bool
     */
    private static function emailFromStringIsValid(string $string): bool
    {
        $email = self::getEmailFromString($string);

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public static function getEmailFromString(string $string): string
    {
        if (preg_match('~<(.*?)>~', $string, $output) !== 0) {
            return trim($output[1]);
        }

        return '';
    }

    /**
     * Update email separator from comma with EMAIL_SEPARATOR_CODE
     * @param string $string
     * @return mixed
     */
    public static function standardiseEmailSeparator($string)
    {
        // Do not replace comma symbols inside "".
        // Double quotes on header are being set on homepage/classList.html. It should be safe to say that we can use to regex?
        return preg_replace('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', \chr(self::EMAIL_SEPARATOR_CODE), $string);
    }

    /**
     * Implode glue to join email addresses array
     * @return string
     */
    public static function implodeGlue(): string
    {
        return \chr(self::EMAIL_SEPARATOR_CODE);
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
                ->headersToIgnore(['message-id', 'date', 'mime-version', 'messageid'])
                ->toArray()
        );
    }
}
