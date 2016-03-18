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


    /**
     * AbstractService constructor.
     */
    public function __construct()
    {
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
     * @throws \Exception
     * @return bool
     */
    public function send(array $tokens, array $data)
    {
        // it's like reconstituting orange juice... it's just not right *sigh*
        $n = new Notification([ Instance::jsonUnserialize($data[0]) ]);

        $id = $n->getId();

        $body  = $n->getMeta('body');

        if ($body) {
            $title = $this->decorator->renderTitleAsPlaintext($n);
        } else {
            $title = 'Your Schoolname';
            $body  = $this->decorator->renderTitleAsPlaintext($n);
        }

        return $this->propagate($tokens, $id, $title, $body);
    }


    /**
     * @param array $tokens
     * @param string $title
     * @param string $body
     * @return bool
     */
    protected abstract function propagate(array $tokens, $id, $title, $body);


}
