<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Log;


use Alaress\Async\Notification\Instance\Builder;
use Alaress_Date_Format;
use Monolog\Logger;
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
        return 'ups';
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
            [ $this, 'notifyStaffLogged' ]
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

        // for now, we only care about logging dry runs
        if ($log->isDry() && $bundle->getMethod() == Mapper::METHOD_CREATE) {
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

        $bob->setTitle("User provisioning dry-run has completed")
            ->setIcon('http://192.168.36.10/images/icon.php?icon=tick&size=72') // TODO find some better image than a tick icon....
            ->setHref('/adminv2/ups/log/'.$log->getId())
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::NOTICE)
            ->addTopic(implode('.', ['ups', $log->getId(), self::TRIGGER_LOGGED]))
            ->setTimestamp($storage->getCreatedAt()->getUnixTimeStamp());

        // forcibly send this message to the user
        $envelope->targetToUser($user->getId(), $bob->build(), true);

        return $envelope;
    }

}
