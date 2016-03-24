<?php

namespace Schoolbox\Pastoral\Comment;

use Alaress\Async\Notification\Instance\Builder;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccessModel;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\RoleMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
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

        // Get the pastoral record the comment relates to

        $pastoral = $this->getPastoralMapper()->findById($model->getPastoralId());
        $owner = $this->getUserMapper()->findById($model->getOwner()->getId());

        $builder = new Builder();
        $builder->setTitle("A pastoral comment has been {$method}")
            ->setIcon("/portrait.php?size=32&userId=" . $owner->getExternalId())
            ->setHref("/pastoral/" . $pastoral->getStudent()->getId() . "/" . $pastoral->getId() . "?ts=" . md5(date("YmdHis")))
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::ERROR)
            ->addTopic("pastoral_comment." . $model->getId() . ".{$method}");

        $message = $builder->build();

        // Work out who to send the message to

        /** @var Collection|PastoralAccessModel $accessModels */
        $accessModels = $this->getPastoralAccessMapper()->findByColumn("pastoral_id", $pastoral->getId());

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
            $envelope->targetToParent($pastoral->getStudent()->getId(), $message);

        return $envelope;
    }

    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies["pastoralMapper"]))
            $dependencies["pastoralMapper"] = PastoralMapper::getInstance();
        if (empty($dependencies["pastoralAccessMapper"]))
            $dependencies["pastoralAccessMapper"] = PastoralAccessMapper::getInstance();
        if (empty($dependencies["roleMapper"]))
            $dependencies["roleMapper"] = RoleMapper::getInstance();
        if (empty($dependencies["userMapper"]))
            $dependencies["userMapper"] = UserMapper::getInstance();

        parent::loadDependencies($dependencies);
    }

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_PASTORAL_COMMENT;
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

    /** @var UserMapper $userMapper */
    protected $userMapper;

    /** @var RoleMapper $roleMapper */
    protected $roleMapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected $pastoralAccessMapper;

    /** @var PastoralMapper $pastoralMapper */
    protected $pastoralMapper;

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
     * @return PastoralMapper
     */
    public function getPastoralMapper() {
        return $this->pastoralMapper;
    }

    /**
     * @param PastoralMapper
     * @return $this
     */
    public function setPastoralMapper($pastoralMapper)
    {
        $this->pastoralMapper = $pastoralMapper;
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

    /**
     * @return UserMapper
     */
    public function getUserMapper() {
        return $this->userMapper;
    }

    /**
     * @param $userMapper
     * @return $this
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }
}