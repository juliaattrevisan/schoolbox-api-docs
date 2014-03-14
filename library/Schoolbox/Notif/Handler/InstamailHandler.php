<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Handler;

use Alaress\Mail\TwigMailer;
use Monolog\Handler\MailHandler;
use Monolog\Logger;
use PhpAmqpLib\Message\AMQPMessage;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Notif\Message\External;
use Schoolbox\Notif\Queue\UnreadMessage;
use Schoolbox\User;
use Schoolbox\UserMapper;


/**
 * Handler for sending instant emails
 *
 * Class InstamailHandler
 * @package Schoolbox\Notif\Handler
 */
class InstamailHandler extends MailHandler {

	protected $conf;
	protected $userMapper;
	protected $coreContext;

	/**
	 * We don't accept a Swift_Message in this constructor, the entire message will be generated on the fly.
	 *
	 * @param int $level
	 * @param bool $bubble
	 */
	public function __construct($level = Logger::ERROR, $bubble = true) {
		parent::__construct($level, $bubble);
		$this->conf = ConfigMapper::getInstance();
		$this->userMapper = UserMapper::getInstance();

		// config context setup

		$neededConfigs = array(
			'title',
			'school_name',
			'site_hostname',
			'site_location',
			'site_domain',
		);

		foreach($neededConfigs as $key) {
			$this->coreContext['config'][$key] = $this->conf->get($key);
		}

		$this->coreContext['config']['year'] = date('Y');
		$this->coreContext['config']['padding'] = 4;

		$site = $this->coreContext['config']['site_location'];

		$possibleLogos = array(
			'email-logo',
			"$site-logo",
			'hercules',
			'logo'
		);

		foreach($possibleLogos as $filename) {
			if(file_exists(SITE_root . "/www/images/$site/$filename.png")) {
				$this->coreContext['config']['logo'] = $filename;
				break;
			}
		}

		if(!isset($this->coreContext['config']['logo'])) {
			$this->coreContext['config']['logo'] = 'logo';
		}


		// extract colours
		$colours = $this->parseLess(SITE_root . "/www/css/core/variables.less");
		$colours = $this->parseLess(SITE_root . "/www/css/$site/chrome.less", $colours);
		$this->coreContext['colours'] = $colours;
	}


	/**
	 * @param string $filename
	 * @param array $colours
	 * @return array
	 */
	protected function parseLess($filename, $colours = array()) {

		$fp = @fopen($filename, 'r');
		if($fp === false) {
			return $colours;
		}
		// iterate and extract colour vars
		while($line = fgets($fp)) {

			$regex = '`
				@                       # vars start with an at
				([a-z\-]+)              # get the varname
				:                       # delimited by a colon
				\s+                     # miscellaneous amounts of whitespace
				(?:                     # $1
					(\#[0-9A-Fa-f]+)    # colour declaration
				|                       # OR
					@([a-z\-]+)         # alternative var reference
				)                       # /$1
				;                       # trailing semicolon delimiter
			`x';

			if(!preg_match($regex, $line, $matches)) {
				continue;
			}

			$matches[1] = str_replace('-','_', $matches[1]);

			if(count($matches) == 3) {
				$colours[$matches[1]] = strtoupper($matches[2]);
			}
			elseif(count($matches) == 4) {
				$matches[3] = str_replace('-','_', $matches[3]);
				if(isset($colours[$matches[3]])) {
					$colours[$matches[1]] = $colours[$matches[3]];
				}
				else {
					$colours[$matches[1]] = '{'.$matches[3].'}';
				}
			}

		}
		fclose($fp);
		return $colours;
	}


	protected function send($content, array $records) {

		$mailer = new TwigMailer('instamail.twig');
		$contexts = array();

		// index records by user and filter inappropriate ones
		foreach($records as $record) {
			if(!preg_match('`^(?:instant|digest).user.([0-9]+)$`', $record['channel'], $matches)) {
				continue;
			}

			$user = $this->userMapper->findById($matches[1]);

			$record['message'] = unserialize($record['message']);
			unset($record['formatted']);

			$data = json_decode($record['message']->serialize(), true);

			// extract title
			$params = $data['params'];
			$title = $data['body']['body'];

			if(is_array($params)) {
				foreach($params as $token => $value) {

					// text and href
					if(is_array($value) && count($value) == 2) {
						$value = array_values($value);
						$text = $value[0];
					}
					// just text
					elseif(is_string($value)) {
						$text = $value;
					}
					// nothing we recognise: skip dat!
					else {
						continue;
					}

					$title = str_replace(":$token", $text, $title);
				}
			}

			$contexts[$user->getId()][] = array($user, $title, $record);

		}

		foreach($contexts as $userId => $userContexts) {
			// need some user context to keep going
			if(!count($userContexts)) {
				continue;
			}

			$mailContext = $this->coreContext;

			$uribase = "http://" . $mailContext['config']['site_domain'];

			if(count($userContexts) == 1) {
				// the plaintext of the message
				$mailer->setSubject($userContexts[0][1]);
			}
			else {
				$mailer->setSubject("Here's What You've Been Missing");
			}

			foreach($userContexts as $values) {
				/** @var User $user */
				list($user, $title, $context) = $values;

				/** @var External $external */
				$external = $context['message'];
				$context['message'] = json_decode($external->serialize(), true);




				$formatted = $context['message'];

				// extract title
				$params = $formatted['params'];
				$messageString = $formatted['body']['body'];

				if(is_array($params)) {
					foreach($params as $token => $value) {

						// text and href
						if(is_array($value) && count($value) == 2) {
							$value = array_values($value);
							$text = sprintf('<a href="%s%s" style="color:%s;">%s</a>',
								$uribase, $value[1], $mailContext['colours']['link'], $value[0]
							);
						}
						// just text
						elseif(is_string($value)) {
							$text = $value;
						}
						// nothing we recognise: skip dat!
						else {
							continue;
						}

						$messageString = str_replace(":$token", $text, $messageString);
					}
				}


				$mailContext['messages'][] = array(
					'text' => $messageString,
					'timestamp' => $context['datetime'],
					'icon' => $uribase . $context['message']['body']['icon'],
					'href' => $uribase . $context['message']['body']['href'],
				);
			}

			$mailer->setContext($mailContext);
			$success = $mailer->send(array(
				'name' => $user->formatName(),
				'email' => $user->getEmail()
			));

//			echo ($success ?"":"have not ") . "sent an email to ".$user->getEmail()."\n";

		}
	}

}
