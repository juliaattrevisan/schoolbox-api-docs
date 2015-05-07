<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Handler;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Lumberjack;
use Monolog\Handler\MailHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmail;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Swift_RfcComplianceException;
use UnderflowException;


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

    /** @var LoggerInterface */
    protected $log;

	/**
	 * We don't accept a Swift_Message in this constructor, the entire message will be generated on the fly.
	 *
     * @param LoggerInterface $logger
	 * @param int             $level
	 * @param bool            $bubble
	 */
	public function __construct(LoggerInterface $logger, $level = Logger::ERROR, $bubble = true) {
		parent::__construct($level, $bubble);
		$this->conf = ConfigMapper::getInstance();
		$this->userMapper = UserMapper::getInstance();
        $this->core = new CoreContext($this->conf);
        $this->log = $logger;
	}


    /**
     * @param string $content
     * @param array  $records
     * @throws Swift_RfcComplianceException
     */
    protected function send($content, array $records) {

	    if(count($records) != 1) {
		    // handle multirecord message - TODO pretty sure this never happens
		    echo "Number of records is ".count($records)."\n";
	    }

		// channel must indicate instant user notification
		if(strpos($records[0]['channel'], 'instant.user.') !== 0) {
			return;
		}

		preg_match('`instant.user.([0-9]+)`', $records[0]['channel'], $matches);

		try {
			$user = $this->userMapper->findById($matches[1]);

            if (!$user->getEmail()) {
                throw new Swift_RfcComplianceException("Empty address '{$user->getEmail()}' for user {$user->getId()}");
            }

            $this->email = new TwigEmail('instantmail.twig');
            $this->email->setLogger($this->log)->setCoreContext($this->core->getContext());

            $decorator = new Decorator();

            if(isset($records[0]['context']['user'])) {
				/** @var User $sender */
				$sender = $this->userMapper->findById($records[0]['context']['user']);
				$this->email->setFrom($sender->getEmail(), $sender->formatName());
			}

			$decorator->appendInstance(Instance::jsonUnserialize($records[0]['message']));

			// there should only be one notification
            $ns = $decorator->getNotifications();

			$this->email->setSubject($decorator->renderTitleAsPlaintext($ns[0]));

			$context = $decorator->renderAsTwigContext($this->email->getCoreContext());
			$context['recipient'] = $user->getFirstname();


			$this->email->setContext($context)->send(array(
				'name' => $user->formatName(),
				'email' => $user->getEmail()
			));

		}
		catch(MapperException $e) {
			echo "Could not find user $matches[1]\n";
		}
		catch(UnderflowException $e) {
			echo "Could not compile message: {$e->getMessage()}\n";
		}

	}

}
