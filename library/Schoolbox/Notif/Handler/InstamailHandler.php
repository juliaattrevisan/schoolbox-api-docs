<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Handler;

use Monolog\Handler\MailHandler;
use Monolog\Logger;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\TwigEmail;
use Schoolbox\Notif\Message\External;
use Schoolbox\User;
use Schoolbox\UserMapper;


/**
 * Handler for sending instant emails
 *
 * Class InstamailHandler
 * @package Schoolbox\Notif\Handler
 */
class InstamailHandler extends MailHandler {

    /** @var IConfigLoader */
	protected $conf;

    /** @var UserMapper */
	protected $userMapper;

    /** @var TwigEmail */
    protected $email;

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
        $this->email = new TwigEmail('instamail.twig');
	}


    /**
     * TODO refactor this method
     *
     * @param string $content
     * @param array $records
     */
    protected function send($content, array $records) {

        $coreContext = $this->email->getCoreContext();

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

			$uribase = "http://" . $this->conf->get('site_domain');

			if(count($userContexts) == 1) {
				// the plaintext of the message
				$this->email->setSubject($userContexts[0][1]);
			}
			else {
                $this->email->setSubject("Here's What You've Been Missing");
			}

            $mailContext = array();

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
                                    $uribase, $value[1], $coreContext['colours']['link'], $value[0]
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

            if(isset($user)) {
                $this->email->setContext($mailContext);
                $this->email->send(array(
                    'name' => $user->formatName(),
                    'email' => $user->getEmail()
                ));
            }
		}
	}

}
