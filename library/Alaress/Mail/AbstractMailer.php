<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Mail;


use Alaress\Exception\IO\IOException;
use Alaress\Lumberjack\Logger;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Swift_Attachment;
use Swift_EmbeddedFile;
use Swift_Mailer;
use Swift_MailTransport;
use Swift_Message;
use Swift_SmtpTransport;
use Swift_SwiftException;
use Swift_Transport;

abstract class AbstractMailer implements IMailer {

    const MAX_RETRIES   = 4;
    const RETRY_TIMEOUT = 1;

	/**
	 * Instance of mail transporter.
	 *
	 * @var Swift_Transport
	 */
	protected static $transport;

	/**
	 * @var IConfigLoader
	 */
	protected static $conf;



	/** @var Swift_Message */
	protected $email;

	/**
	 * List of headers that are added to the email that is sent.
	 *
	 * @var array
	 */
	protected $headers;

    /** @var LoggerInterface */
    protected $log;


	/**
	 * Construct the basics of a new Mailer
	 *
	 * @param $template
	 * @param string $subject
	 * @param string $to
	 * @param string $host
	 */
	public function __construct($template, $subject = "", $to = "", $host = "") {
		$this->email = new Swift_Message();
		$this->setFrom(self::getConfig()->get('mail_from_address'), self::getConfig()->get('mail_from_name'));

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


	public function setTo ($to) {
		if ( is_array($to) ) {
			if ( $to['email'] ) {
				if ( $to['name'] ) {
					$this->email->addTo($to['email'], $to['name']);
				}
				else {
					$this->email->addTo($to['email']);
				}
			}
			else {

				$this->to = '';
				foreach ( $to as $email ) {
					if ( $email['name'] ) {
						$this->email->addTo($email['email'], $email['name']);
					}
					else {
						$this->email->addTo($email['email']);
					}
				}
			}
		}
		else {
			$this->email->setTo(self::parseAddressString($to));
		}
	}

	public function setSubject ($subject) {
		$this->email->setSubject($subject);
	}

	/**
	 * @param string $email
	 * @param string $name
	 */
	public function setFrom($email, $name = "") {
		if ($name) {
			$this->email->setFrom($email, $name);
		}
		else {
			$this->email->setFrom($email);
		}
		$this->email->setReturnPath($email);
	}

	/**
	 * Convenience method for setting this mailer's From address as the currently logged in user.
	 *
	 * @param array $sessionData
	 * @return $this
	 */
	public function setFromAsCurrentUser($sessionData = null) {
		$sessionData = $sessionData ?: $_SESSION;

		/** @var User $user */
		$user = $sessionData['user'];
		$this->setFrom($user->getEmail(), $user->formatName());
		return $this;
	}


	public function addFile ($file, $type = 'text/plain', $filename = '', $isFile = true) {
		//add attachment to email
		if ($isFile) {
			$attachment = Swift_Attachment::fromPath($file, $type);
		}
		else {
			$attachment = Swift_Attachment::newInstance($file, null, $type);
		}
		if ($filename) {
			$attachment->setFilename($filename);
		}
		$this->email->attach($attachment);
	}

	public function addImage ($file, $type = 'application/octet') {
		//add inline image to email
		// Returns a string so its source can be used later
		return $this->email->embed(Swift_EmbeddedFile::fromPath($file)->setContentType($type));
	}


	/**
	 * Returns the HTML rendering of the templated email constructed by this object.
	 *
	 * @return string
	 */
	protected abstract function composeHTML();


	/**
	 * Returns the plaintext rendering of the templated email constructed by this object.
	 *
	 * @return string
	 */
	protected abstract function composePlain();


    /**
     * Attempts to send the email constructed by this class. Returns mailing success or failure.
     *
     * @param string $to
     * @throws Swift_SwiftException
     * @return bool
     */
	public function send ($to = "") {

		if ( !empty( $to ) ) {
			$this->setTo($to);
		}

		// construct body and set headers
		$this->email->setBody($this->composePlain(), null, 'utf-8');
		$this->email->addPart($this->composeHTML(), 'text/html', 'utf-8');
		foreach ($this->headers as $name => $value) {
			$this->email->getHeaders()->addTextHeader($name, $value);
		}

        try {
            return $this->trySend(self::MAX_RETRIES);
        } catch (IOException $e) {
            return $this->trySend();
        } catch (Swift_SwiftException $e) {
            throw new Swift_SwiftException("Failed to send email", $e->getCode(), $e);
        }
	}


    /**
     * @param int  $retries
     * @param bool $reinitialise
     * @throws \Exception
     * @return bool
     */
    private function trySend($retries = 0, $reinitialise = false)
    {
        // create transport and send email
        try {
            $mailer = Swift_Mailer::newInstance(self::getSwiftTransport($reinitialise));
            $this->configureSwiftMailer($mailer);

            $mailer->send($this->email);
        }
        catch (\Exception $e) {
            // this means "mail server went away"
            $errMsg = sprintf("Internal Mailing %s occurred (%d %s): %s",
                get_class($e), $retries, $retries == 1 ? 'retry remains' : 'retries remain', $e->getMessage()
            );

            $this->log->error($errMsg, [ $e ]);

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
	 * @param Swift_Mailer $mailer
	 */
	protected function configureSwiftMailer($mailer) {
		// noop - to be overridden
	}



	/**
	 * @param IConfigLoader $conf
	 * @param bool $reinitialise
	 * @return Swift_Transport
	 */
	public static function getConfiguredSwiftTransport($conf, $reinitialise = false) {
		if (!(self::$transport instanceof Swift_Transport) || $reinitialise) {
			if ($conf->get('mail_smtp_host') && $conf->get('mail_smtp_port') ){
				self::$transport = new Swift_SmtpTransport(
					$conf->get('mail_smtp_host'), $conf->get('mail_smtp_port')
				);
				self::$transport->setLocalDomain(
					$conf->get('site_hostname').'.'.$conf->get('site_domain')
				);

				//Set auth details if available
				if($conf->get('mail_smtp_username') && $conf->get('mail_smtp_password')) {
					self::$transport->setUsername($conf->get('mail_smtp_username'));
					self::$transport->setPassword($conf->get('mail_smtp_password'));
				}
			}
			else {
				self::$transport = new Swift_MailTransport();
			}
		}
		return self::$transport;
	}

	/**
	 * @param bool $reinitialise
	 * @return Swift_Transport
	 */
	public static function getSwiftTransport($reinitialise = false) {
		return static::getConfiguredSwiftTransport(self::getConfig(), $reinitialise);
	}

	/**
	 * @param string $string
	 * @return array
	 */
	public static function parseAddressString($string) {
		$address_list = imap_rfc822_parse_adrlist($string, self::getConfig()->get('site_hostname').'.'.self::getConfig()->get('site_domain'));
		$return_list  = array();
		foreach ($address_list as $address) {
			if ($address->host !== '.SYNTAX-ERROR.') {
				$to    = $address->mailbox.'@'.$address->host;
				$alias = property_exists($address, 'personal') ? $address->personal : null;
				$return_list[$to] = $alias;
			}
		}
		return $return_list;
	}

	/**
	 * @param string $address
	 * @param string $name
	 * @return string
	 */
	public static function buildAddress($address, $name = '') {
		list($mailbox, $host) = explode('@', $address, 2);
		return imap_rfc822_write_address($mailbox, $host, $name);
	}


	/**
	 * @return IConfigLoader
	 */
	protected static function getConfig() {
		if(!self::$conf instanceof IConfigLoader) {
			self::$conf = ConfigMapper::getInstance();
		}
		return self::$conf;
	}
} 