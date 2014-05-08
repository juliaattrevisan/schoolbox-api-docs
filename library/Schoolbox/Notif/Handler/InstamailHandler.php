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
use Schoolbox\Mapper\Exception;
use Schoolbox\Notif\Message\Decorator;
use Schoolbox\Notif\Message\External\TargetedMessage;
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
        $this->email = new TwigEmail('instantmail.twig');
	}


    /**
     * @param string $content
     * @param array $records
     */
    protected function send($content, array $records) {

        $coreContext = $this->email->getCoreContext();

//		$contexts = array();
	    $uribase = "http://" . $this->conf->get('site_domain');

	    $decor = new Decorator(null);

		// index records by user and filter inappropriate ones
		foreach($records as $record) {
			// channel must indicate instant user notification
			if(strpos($record['channel'], 'instant.user.') !== 0) {
				continue;
			}

			preg_match('`instant.user.([0-9]+)`', $record['channel'], $matches);

			try {
				$user = $this->userMapper->findById($matches[1]);

				if(isset($record['context']['user'])) {
					/** @var User $sender */
					$sender = $this->userMapper->findById($record['context']['user']);
					$this->email->setFrom($sender->getEmail(), $sender->formatName());
				}

				/** @var TargetedMessage $message */
				$message = unserialize($record['message']);
				$decor->setMessage($message);

				$this->email->setSubject($decor->formatPlain());

				$context = array(
					'text' => $decor->formatMail($uribase, $coreContext['colours']['link']),
					'date' => $decor->getAlaressDateFormat()->getAustralian(),
					'icon' => $uribase . $decor->getIcon(),
					'href' => $uribase . $decor->getHref(),
				);

				$this->email->setContext($context)->send(array(
					'name' => $user->formatName(),
					'email' => $user->getEmail()
				));

			}
			catch(Exception $e) {
				echo "Could not find user $matches[1]\n";
			}

		}
	}

}
