<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Handler;
use Monolog\Handler\SwiftMailerHandler;
use Monolog\Logger;
use Schoolbox\Notif\Message\External;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;


/**
 * Handler for sending instant emails
 *
 * Class InstamailHandler
 * @package Schoolbox\Notif\Handler
 */
class InstamailHandler extends SwiftMailerHandler {

	protected $conf;
	protected $userMapper;

	/**
	 * We don't accept a Swift_Message in this constructor, the entire message will be generated on the fly.
	 *
	 * @param \Swift_Mailer $mailer
	 * @param int $level
	 * @param bool $bubble
	 */
	public function __construct(\Swift_Mailer $mailer, $level = Logger::ERROR, $bubble = true) {
		parent::__construct($mailer, new \Swift_Message(), $level, $bubble);
		$this->conf = ConfigMapper::getInstance();
		$this->userMapper = UserMapper::getInstance();
	}


	protected function send($content, array $records) {

		/** @var \Swift_Message[] $emails */
		$emails = array();

		foreach($records as $record) {
			$record['message'] = unserialize($record['message']);

			if(!preg_match('`^instant.user.([0-9]+)$`', $record['channel'], $matches)) {
				continue;
			}

			$userId = $matches[1];

			if(!isset($emails[$userId])) {
				try {
					$emails[$userId] = clone $this->message;

					/** @var User $user */
					$user = $this->userMapper->findById($userId);

					$emails[$userId]
						->setTo($user->getEmail(), $user->formatName())
						->setFrom($this->conf->get('mail_from_address'), $this->conf->get('mail_from_name'));
				}
				catch(\Swift_RfcComplianceException $e) {
					// email invalid
					echo $e->getMessage();
				}
			}

			/** @var External $external */
			$external = $record['message'];

			$data = json_decode($external->serialize(), true);

			$protocol = 'http';
			$uribase = $this->conf->get('site_domain');
			if ($hostname = $this->conf->get('site_hostname')) {
				$uribase = $hostname . '.' . $this->conf->get('site_domain');
			}

			$params = $data['params'];
			$body = $data['body']['body'];
			$title = $data['body']['body'];

			$icon = $data['body']['icon'];
			$href = $data['body']['href'];

			if(is_array($params)) {
				foreach($params as $token => $value) {

					// text and href
					if(is_array($value) && count($value) == 2) {
						$value = array_values($value);
						$a = sprintf('<a href="%s://%s%s">%s</a>', $protocol, $uribase, $value[1], $value[0]);
						$b = $value[0];
					}
					// just text
					elseif(is_string($value)) {
						$a = sprintf('<span>%s</span>', $value);
						$b = $value;
					}
					// nothing we recognise: skip dat!
					else {
						continue;
					}

					$match = ":$token";
					$body = str_replace($match, $a, $body);
					$title = str_replace($match, $b, $title);
				}
			}

			var_dump($body);
			var_dump($title);
			var_dump("$protocol://$uribase" . $icon);
			var_dump("$protocol://$uribase" . $href);



			try {
				$emails[$userId]
					->setSubject($title)
					->setBody($body, 'text/html', 'utf-8');
			}
			catch(\Swift_RfcComplianceException $e) {
				// email invalid
				echo $e->getMessage();
			}
		}

		foreach($emails as $email) {
			try {
				$this->mailer->send($email);
			}
			catch(\Swift_SwiftException $e) {
				// failed to send
				echo $e->getMessage();
			}
		}

	}
}