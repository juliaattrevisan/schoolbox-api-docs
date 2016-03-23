<?php

namespace Schoolbox\Pastoral\Pastoral;

use Alaress\Async\Notification\Instance\Builder;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Access\Mapper as AccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccessModel;
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
     * @param Model $model
     * @param string $method
     * @return Envelope
     */
    protected function buildMessage($model, $method)
    {
        $envelope = new Envelope();

        $builder = new Builder();
        $builder->setTitle("A pastoral care record has been {$method}")
            ->setIcon("/portrait.php?size=32&userId=" . $model->getStudent()->getExternalId())
            ->setHref("/pastoral/" . $model->getStudent()->getId())
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::ERROR)
            ->addTopic("pastoral." . $model->getId() . ".{$method}");

        $message = $builder->build();

        // Work out who to send the message to

        /** @var Collection|PastoralAccessModel $accessModels */
        $accessModels = $this->accessMapper->findByColumn("pastoral_id", $model->getId());

        $sendToParents = false;

        /** @var PastoralAccessModel $accessModel */
        foreach ($accessModels as $accessModel) {

            if ($accessModel->getRole()->isStaff()) {
                $envelope->broadcastToRole($accessModel->getRole()->getId(), $message);
            } else if ($accessModel->getRole()->isParent()) {
                $sendToParents = true;
            }
        }

        $envelope->targetToParent($model->getStudent()->getId(), $message);
        $envelope->targetToUser(19, $message);

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
            case BaseMapper::METHOD_CREATE:
                $bundle->attach(self::TRIGGER_CREATED, self::IMMEDIATELY);
                break;
            case BaseMapper::METHOD_UPDATE:
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
        return $this->buildMessage($model, self::TRIGGER_CREATED);
    }

    /**
     * @param Model $model
     * @param User $userEffected
     * @param array $diff
     * @return Envelope
     */
    public function notifyUpdated($model, $userEffected, $diff=[])
    {
        return $this->buildMessage($model, self::TRIGGER_UPDATED);
    }

    /**
     * @param string $label
     * @return array
     */
    public function retrieveCallbacks($label)
    {
        return [
            [ $this, "notify" . ucfirst($label) ]
        ];
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