<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Log;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress_Date_Format;
use Monolog\Logger;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class Notifier extends AbstractModuleNotifier
{
    const TRIGGER_LOGGED = 'logged';

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_UPS;
    }


    /**
     * @param Mapper[] $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        parent::loadDependencies($dependencies);
    }


    /**
     * Bypass most checks, there's no SQL config for this
     *
     * @param string $label
     * @return array
     */
    public function retrieveCallbacks($label)
    {
        return [
            [$this, 'notifyStaffLogged'],
        ];
    }


    /**
     * @param TriggerBundleInterface $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        /** @var Model $log */
        $log = $bundle->getEvent()->getObject();

        if ($bundle->getMethod() == Mapper::METHOD_CREATE) {
            $bundle->attach(self::TRIGGER_LOGGED, self::IMMEDIATELY);
        }
    }


    /**
     * @param Model $log
     * @param User  $user
     * @return Envelope
     */
    public function notifyStaffLogged($log, $user)
    {
        $envelope = new Envelope();

        $bob = new Builder();

        $storage = $log->getStorage();

        // hydration shouldn't be necessary....... and yet...
        if (!$storage->getCreatedAt() instanceof Alaress_Date_Format) {
            $storage = Storage\Mapper::getInstance()->findByHash($storage->getHash());
        }

        $bob->setTitle(
            $log->isDry()
                ? 'User provisioning dry run has completed'
                : 'User provisioning live update has completed'
            )
            ->setIcon('/images/icon.php?size=' . $this->iconSize . '&icon=' . NSMapper::NS_UPS)
            ->setHref('/adminv2/ups/log/' . $log->getId() . '.html')
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::NOTICE)
            ->addTopic(implode('.', ['ups', $log->getId(), self::TRIGGER_LOGGED]))
            ->setTimestamp($storage->getCreatedAt()->getUnixTimeStamp())
            ->setAllowSelfSend(true);

        // forcibly send this message to the user
        $envelope->targetToUser($user->getId(), $bob->build(), Instance::DELIVERY_INSTANT);

        return $envelope;
    }

}
