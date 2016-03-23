<?php

namespace Schoolbox\Pastoral\Pastoral;

use Alaress\Async\Notification\Instance\Builder;
use Monolog\Logger;
use Schoolbox\Mapper;
use Schoolbox\Pastoral\Access\Mapper as AccessMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;

class Notifier extends AbstractModuleNotifier
{
    const TRIGGER_CREATED = 'created';
    const TRIGGER_UPDATED = 'updated';

    /**
     * @param int $id
     * @param string $method
     * @return Envelope
     */
    protected function buildMessage($id, $method)
    {
        $envelope = new Envelope();
        $builder = new Builder();

        $access = $this->accessMapper->findByColumn("pastoral_id", $id);

        $builder->setTitle("yay")
            ->setIcon("")
            ->setHref("http://www.wombles.com")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::ERROR)
            ->addTopic("pastoral.{$id}.{$method}");

        $message = $builder->build();
        $envelope->broadcastToRole(1, $message);
        /*
        $envelope->targetToParent($studentid, $message);
        $envelope->targetToUser($user, $message);
        */
        return $envelope;
    }

    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies["accessMapper"]))
            $dependencies["accessMapper"] = AccessMapper::getInstance();

        parent::loadDependencies($dependencies);
    }

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_PASTORAL;
    }

    /**
     * @param TriggerBundleInterface $bundle
     */
    public function bundleTriggers($bundle)
    {
        switch ($bundle->getMethod()) {
            case Mapper::METHOD_CREATE:
                $bundle->attach(self::TRIGGER_CREATED, self::IMMEDIATELY);
                break;
            case Mapper::METHOD_UPDATE:
                $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
                break;
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
        return $this->buildMessage($model->getId(), self::TRIGGER_CREATED);
    }

    /**
     * @param Model $model
     * @param User $userEffected
     * @param array $diff
     * @return Envelope
     */
    public function notifyUpdated($model, $userEffected, $diff=[])
    {
        return $this->buildMessage($model->getId(), self::TRIGGER_UPDATED);
    }

    /**
     * @param string $label
     * @return array
     */
    public function retrieveCallbacks($label)
    {
        return [$this, "notify{$label}"];
    }

    // Here be the getters/setter

    /** @var AccessMapper $accessMapper */
    protected $accessMapper;

    /**
     * @return AccessMapper
     */
    public function getAccessMapper() {
        return $this->accessMapper;
    }

    /**
     * @param $accessMapper
     * @return $this
     */
    public function setAccessMapper($accessMapper)
    {
        $this->accessMapper = $accessMapper;
        return $this;
    }
}