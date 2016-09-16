<?php

namespace Schoolbox\Pastoral;

use Alaress\Async\Notification\Instance\Builder;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccessModel;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

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

        // Get hydrated pastoral record

        $pastoral = $this->getPastoralMapper()->findById($model->getId());

        // Get the kids teachers

        $student = $this->getUserMapper()->findById($model->getStudent()->getId());
        $student->loadTeachers();
        $student->loadParents();
        $teachers = $student->getTeacherCollection();

        // Create the message

        $builder = new Builder();
        $builder->setTitle("A pastoral care record has been {$method}")
            ->setIcon("/portrait.php?size=32&userId=" . $pastoral->getOwner()->getExternalId())
            ->setHref("/pastoral/" . $model->getStudent()->getId() . "/" . $model->getId() . "?ts=" . md5(date("YmdHis")))
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::ERROR)
            ->addTopic("pastoral." . $model->getId() . ".{$method}");

        $message = $builder->build();

        // Work out who to send the message to

        $sendToParents = false;

        /** @var Collection|PastoralAccessModel $accessModels */
        $accessModels = $this->getPastoralAccessMapper()->findByColumn("pastoral_id", $model->getId());

        /** @var PastoralAccessModel $accessModel */
        foreach ($accessModels as $accessModel) {
            $roleId = $accessModel->getRole()->getId();

            if (!empty($roleId)) {
                // Sending to a role

                /** @var Role $role */
                $role = $this->getRoleMapper()->findById($roleId);

                // The system should only notify staff if they are a teacher of the student

                if ($role->isStaff()) {
                    /** @var User $teacher */
                    foreach ($teachers as $teacher) {
                        if ($teacher->getRole()->getId() == $roleId) {
                            $envelope->targetToUser($teacher->getId(), $message);
                        }
                    }
                } else {
                    if ($role->isParent()) {
                        $sendToParents = true;
                    }
                }
            } else {
                // Sending to individual users

                $skip = false;

                // If its private, and the selected user is a parent, don't notify them

                // Get hydrated user so we can work out their role

                /** @var \Schoolbox\User $user */
                $user = $this->getUserMapper()->findOneByColumn("id", $accessModel->getUser()->getId());

                /** @var Role $role */
                $role = $this->getRoleMapper()->findOneByColumn("id", $user->getRole()->getId());

                if ($role->isParent() && $pastoral->getPrivate()) {
                    $skip = true;
                }

                // Otherwise, send it to the user

                if (!$skip) {
                    $envelope->targetToUser($accessModel->getUser()->getId(), $message);
                }
            }
        }

        // Just make sure parents don't ever get private notifications

        if ($sendToParents && !$pastoral->getPrivate()) {
            $envelope->targetToParent($model->getStudent()->getId(), $message);
        }

        return $envelope;
    }

    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies["userMapper"])) {
            $dependencies["userMapper"] = UserMapper::getInstance();
        }
        if (empty($dependencies["pastoralMapper"])) {
            $dependencies["pastoralMapper"] = PastoralMapper::getInstance();
        }
        if (empty($dependencies["pastoralAccessMapper"])) {
            $dependencies["pastoralAccessMapper"] = PastoralAccessMapper::getInstance();
        }
        if (empty($dependencies["roleMapper"])) {
            $dependencies["roleMapper"] = RoleMapper::getInstance();
        }

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
    public function notifyCreated($model, $userEffected, $diff = [])
    {
        return $this->buildMessage($model, self::TRIGGER_CREATED);
    }

    /**
     * @param Model $model
     * @param Model $model
     * @param User $userEffected
     * @param array $diff
     * @return Envelope
     */
    public function notifyUpdated($model, $userEffected, $diff = [])
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
            [$this, "notify" . ucfirst($label)]
        ];
    }

    // Here be the getters/setter

    /**
     * @var RoleMapper $roleMapper ;
     */
    protected $roleMapper;

    /**
     * @var UserMapper $userMapper ;
     */
    protected $userMapper;

    /** @var PastoralMapper $pastoralMapper */
    protected $pastoralMapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected $pastoralAccessMapper;

    /**
     * @return PastoralAccessMapper
     */
    public function getPastoralAccessMapper()
    {
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
    public function getRoleMapper()
    {
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
    public function getUserMapper()
    {
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

    /**
     * @return PastoralMapper
     */
    public function getPastoralMapper()
    {
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

}