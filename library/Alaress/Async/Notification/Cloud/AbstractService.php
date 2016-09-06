<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
     * @var LoggerInterface
     */
    private $log;

    /** @var Decorator */
    protected $decorator;

    /** @var RegistryMapper */
    private $registryMapper;


    /**
     * AbstractService constructor.
     *
     * @param RegistryMapper $registryMapper
     */
    public function __construct(RegistryMapper $registryMapper)
    {
        $this->registryMapper = $registryMapper;
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
            $this->setLog(new Logger(null));
        }
        return $this->log;
    }


    /**
     * @param array $tokens
     * @param array $data
     * @param int   $unreadCount
     * @throws \Exception
     * @return bool
     */
    public function send(array $tokens, array $data, $unreadCount = 0)
    {
        // it's like reconstituting orange juice... it's just not right *sigh*
        $n = new Notification([ Instance::jsonUnserialize($data[0]) ]);

        $id = $n->getId();

        $title = $this->decorator->renderTitleAsPlaintext($n);

        return $this->propagate($tokens, $id, $title, $unreadCount);
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
     * @param array  $tokens
     * @param int    $id
     * @param string $title
     * @param int    $unreadCount
     * @return bool
     */
    protected abstract function propagate(array $tokens, $id, $title, $unreadCount = 0);

}
