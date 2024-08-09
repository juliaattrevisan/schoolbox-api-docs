<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Notification\Cloud;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Notification;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\Realtime\Notification\Decorator;

abstract class AbstractService implements ServiceInterface
{
    /**
     * Yes, this is private on purpose. See the protected accessor for the why.
     *
     * @var LoggerInterface|null
     */
    private $log;

    /** @var Decorator */
    protected $decorator;

    /** @var RegistryMapper */
    private $registryMapper;

    /** @var string */
    private $appIdentifier;


    /**
     * AbstractService constructor.
     *
     * @param RegistryMapper $registryMapper
     * @param string         $appIdentifier
     */
    public function __construct(RegistryMapper $registryMapper, $appIdentifier)
    {
        $this->registryMapper = $registryMapper;
        $this->appIdentifier = $appIdentifier;
        $this->decorator = new Decorator();
    }


    /**
     * @param LoggerInterface $log
     * @return self
     */
    public function setLog(LoggerInterface $log)
    {
        $this->log = $log;
        return $this;
    }


    /**
     * @return LoggerInterface
     */
    protected function getLog()
    {
        // ensure there's at least a dummy logger there
        if (empty($this->log)) {
            $this->setLog(new Logger('dummy'));
        }
        return $this->log;
    }


    /**
     * @return string
     */
    public function getAppIdentifier()
    {
        return $this->appIdentifier;
    }


    /**
     * @param array $tokens
     * @param Instance $instance
     * @param int   $unreadCount
     * @throws \Exception
     * @return bool
     */
    public function send(array $tokens, Instance $instance, $unreadCount = 0, ?string $recipientExternalId = null)
    {
        // it's like reconstituting orange juice... it's just not right *sigh*
        $n = new Notification([ $instance ]);

        $id = $n->getId();

        $title = $this->decorator->renderTitleAsPlaintext($n);

        return $this->propagate($tokens, $id, $title, $unreadCount, $n, $recipientExternalId);
    }


    /**
     * @param string $token
     * @param string $status
     * @param int    $severity
     * @param string $char
     * @return void
     */
    protected function report($token, $status, $severity, $char = null)
    {
        $class = get_class($this);
        $class = substr($class, strrpos($class, '\\') + 1);

        $context = [];

        if ($char) {
            $context['char'] = $char;
        }

        $this->getLog()->log($severity, "$class response for $token: $status", $context);
    }


    /**
     * @param string $token
     * @return void
     */
    protected function deleteToken($token)
    {
        $this->registryMapper->deleteByColumn('app_token', $token);
    }

    /**
     * @param array             $tokens
     * @param int               $id
     * @param string            $title
     * @param int               $unreadCount
     * @param Notification|null $notification
     * @return bool
     */
    abstract protected function propagate(array $tokens, $id, $title, $unreadCount = 0, ?Notification $notification = null, ?string $recipientExternalId = null);
}
