<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Test;

use Alaress\Async\Notification\Instance\Builder;
use Monolog\Logger;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;

/**
 * Test notifier.
 *
 * @package Schoolbox\Test
 */
class TestNotifier extends AbstractModuleNotifier
{
    const TRIGGER_NOTIFIED = 'notified';

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_TEST;
    }

    /**
     * @param TriggerBundleInterface $bundle
     */
    public function bundleTriggers($bundle)
    {
        $bundle->attach(self::TRIGGER_NOTIFIED, self::IMMEDIATELY);
    }

    /**
     * @param TestModel $object
     * @param User      $user
     * @return Envelope
     */
    public function notifyStaffNotified(TestModel $object, User $user)
    {
        return $this->notifyNotified($object, $user);
    }

    /**
     * @param  TestModel $object
     * @param  User $user
     * @return Envelope
     */
    public function notifyStudentNotified(TestModel $object, User $user)
    {
        return $this->notifyNotified($object, $user);
    }

    /**
     * @param  TestModel $object
     * @param  User $user
     * @return Envelope
     */
    protected function notifyNotified(TestModel $object, User $user)
    {
        $envelope = new Envelope();
        $bob = new Builder();
        $bob->setTitle('A test notification has been made')
            ->setIcon("/portrait.php?size=square64&userId={$user->getExternalId()}")
            ->setHref('#')
            ->setSeverity(Logger::NOTICE)
            ->setNamespace($this->getSlug())
            ->addTopic($this->getSlug() . '.' . $object->getId() . '.notified');
        $message = $bob->build();
        $envelope->targetToUser($user, $message);
        return $envelope;
    }
}
