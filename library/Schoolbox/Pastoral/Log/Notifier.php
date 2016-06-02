<?php

namespace Schoolbox\Pastoral\Log;

use Alaress\Async\Notification\Instance\Builder;
use Monolog\Logger;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;

class Notifier extends AbstractModuleNotifier
{
    const TRIGGER_CREATED = 'created';

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_PASTORAL_LOG;
    }

    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        parent::loadDependencies($dependencies);
    }

    /**
     * @param string $label
     * @return array
     */
    public function retrieveCallbacks($label)
    {
        return [
            [ $this, "notifyCreated" ]
        ];
    }

    /**
     * @param TriggerBundleInterface $bundle
     */
    public function bundleTriggers($bundle)
    {
        if ($bundle->getMethod() == Mapper::METHOD_CREATE) {
            $bundle->attach(self::TRIGGER_CREATED, self::IMMEDIATELY);
        }
    }

    /**
     * @param Model $model
     * @param User $userEffected
     * @param array $diff
     * @return Envelope
     */
    public function notifyCreated($model, $userEffected, $diff=[])
    {
        $envelope = new Envelope();

        // Create the message

        $builder = new Builder();
        $builder->setTitle("Pastoral care import completed")
            ->setIcon("/images/icon.php?size=48&icon=bolt")
            ->setHref("/adminv2/import-pastoral/log/{$model->getImportId()}")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::ERROR)
            ->addTopic("pastoral_log.{$model->getImportId()}.created");

        $message = $builder->build();
        $envelope->targetToUser($model->getUserId(), $message, true);

        return $envelope;
    }

}