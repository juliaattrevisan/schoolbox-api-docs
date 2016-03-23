<?php

namespace Schoolbox\Pastoral\Pastoral;

use Alaress\Async\Notification\Instance\Builder;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccessModel;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
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
            ->setHref("/pastoral/" . $model->getStudent()->getId() . "/" . $model->getId())
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::ERROR)
            ->addTopic("pastoral." . $model->getId() . ".{$method}");

        $message = $builder->build();

        // Work out who to send the message to

        /** @var Collection|PastoralAccessModel $accessModels */
        $accessModels = $this->getPastoralAccessMapper()->findByColumn("pastoral_id", $model->getId());

        $sendToParents = false;

        /** @var PastoralAccessModel $accessModel */
        foreach ($accessModels as $accessModel) {
            $roleId = $accessModel->getRole()->getId();

            if (!empty($roleId)) {

                /** @var Role $role */
                $role = $this->getRoleMapper()->findById($roleId);

                $accessModel->setRole($role);

                if ($accessModel->getRole()->isStaff()) {
                    $envelope->broadcastToRole($accessModel->getRole()->getId(), $message);
                } else {
                    if ($accessModel->getRole()->isParent()) {
                        $sendToParents = true;
                    }
                }
            } else {
                $envelope->targetToUser($accessModel->getUser()->getId(), $message);
            }
        }

        if ($sendToParents)
            $envelope->targetToParent($model->getStudent()->getId(), $message);

        return $envelope;
    }

    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies["pastoralAccessMapper"]))
            $dependencies["pastoralAccessMapper"] = PastoralAccessMapper::getInstance();
        if (empty($dependencies["roleMapper"]))
            $dependencies["roleMapper"] = RoleMapper::getInstance();

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

    /**
     * @var RoleMapper $roleMapper;
     */
    protected $roleMapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected $pastoralAccessMapper;

    /**
     * @return PastoralAccessMapper
     */
    public function getPastoralAccessMapper() {
        return $this->pastoralAccessMapper;
    }

    /**
     * @param $pastoralAccessMapper
     * @return $this
     */
    public function setPastoralAccessMapper($pastoralAccessMapper)
    {
        $this->pastoralAccessMapper = $pastoralAccessMapper;
        return $this;
    }

    /**
     * @return RoleMapper
     */
    public function getRoleMapper() {
        return $this->roleMapper;
    }

    /**
     * @param $roleMapper
     * @return $this
     */
    public function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
        return $this;
    }
}