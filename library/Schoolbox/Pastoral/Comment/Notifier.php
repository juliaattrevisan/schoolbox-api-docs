<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Comment;

use Alaress\Async\Notification\Instance\Builder;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\Pastoral\Model as Pastoral;
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

    /** @var Mapper */
    protected $mapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected $pastoralAccessMapper;

    /** @var PastoralGroupMapper $pastoralGroupMapper */
    protected $pastoralGroupMapper;

    /** @var Mapper $pastoralMapper */
    protected $pastoralMapper;

    /** @var RoleMapper $roleMapper */
    protected $roleMapper;

    /** @var UserMapper $userMapper */
    protected $userMapper;

    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies["pastoralMapper"])) {
            $dependencies["pastoralMapper"] = PastoralMapper::getInstance();
        }

        if (empty($dependencies["pastoralAccessMapper"])) {
            $dependencies["pastoralAccessMapper"] = PastoralAccessMapper::getInstance();
        }

        if (empty($dependencies["pastoralGroupMapper"])) {
            $dependencies["pastoralGroupMapper"] = PastoralGroupMapper::getInstance();
        }

        if (empty($dependencies["roleMapper"])) {
            $dependencies["roleMapper"] = RoleMapper::getInstance();
        }

        if (empty($dependencies["userMapper"])) {
            $dependencies["userMapper"] = UserMapper::getInstance();
        }

        parent::loadDependencies($dependencies);
    }

    /**
     * @param Model  $model
     * @param string $method
     *
     * @return Envelope
     */
    protected function buildMessage($model, $method)
    {
        $envelope = new Envelope();

        // Get the pastoral record the comment relates to

        /** @var Pastoral $pastoral */
        $pastoral = $this->pastoralMapper->findById($model->getPastoral()->getId());
        $owner    = $this->userMapper->findById($model->getOwner()->getId());

        // Get the kids teachers

        $student = $this->userMapper->findById($pastoral->getStudent()->getId());
        $student->loadTeachers();
        $teachers = $student->getTeacherCollection();

        // Create the message

        $timestamp = md5(date("YmdHis"));

        $private = $pastoral->getPrivate() ? "private " : "";

        $builder = new Builder();
        $builder->setTitle("A {$private}pastoral comment has been {$method}")
            ->setIcon("/portrait.php?size=square32&userId={$owner->getExternalId()}")
            ->setHref("/pastoral/student/{$pastoral->getStudent()->getId()}/pastoral/{$pastoral->getId()}?ts={$timestamp}")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::ERROR)
            ->addTopic("pastoral_comment.{$model->getId()}.{$method}");

        $message = $builder->build();

        // Work out who to send the message to

        /** @var Collection|PastoralAccess[] $accessRecords */
        $accessRecords = $this->pastoralAccessMapper->findByColumn("pastoral_id", $pastoral->getId());

        /** @var PastoralAccess $pastoralAccess */
        foreach ($accessRecords as $pastoralAccess) {
            $role          = $pastoralAccess->getRole();
            $pastoralGroup = $pastoralAccess->getGroup();
            $user          = $pastoralAccess->getUser();

            try {
                if ($role instanceof Role) {
                    if ($role->isStaff()) {
                        /** @var User $user */
                        foreach ($teachers as $user) {
                            if ($user->getRole()->getId() == $role->getId()) {
                                // Make sure the role can see this comment if it is private

                                if ($pastoralAccess->getPrivate() || !$model->getPrivate()) {
                                    $envelope->targetToUser($user->getId(), $message);
                                }
                            }
                        }
                    }
                } elseif ($pastoralGroup instanceof PastoralGroup) {
                    // Sending to a pastoral group

                    /** @var PastoralGroup $pastoralGroup */
                    $pastoralGroup = $this->pastoralGroupMapper->findById($pastoralGroup->getId());

                    /** @var PastoralGroupMember $member */
                    foreach ($pastoralGroup->getMembers() as $member) {
                        // Only staff should be in pastoral groups

                        $user = $member->getUser();

                        if ($user->getRole()->isStaff()) {
                            $envelope->targetToUser($user->getId(), $message);
                        }
                    }
                } elseif ($user instanceof User) {
                    // Only staff ever have access to pastoral comments

                    if ($user->getRole()->isStaff()) {
                        $envelope->targetToUser($user->getId(), $message);
                    }
                }
            } catch (MapperException $e) {
                // Log an error and move on so we don't break the rest of the notifications
                $this->log->error("Error: Unable to send pastoral comment {$method} notification. {$e->getMessage()}", [$e]);
            }
        }

        return $envelope;
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
     * @param User  $effector
     * @param array $diff
     *
     * @return Envelope
     */
    public function notifyCreated(Model $model, User $effector, $diff = [])
    {
        return $this->buildMessage($model, self::TRIGGER_CREATED);
    }

    /**
     * @param Model $model
     * @param User  $effector
     * @param array $diff
     *
     * @return Envelope
     */
    public function notifyUpdated(Model $model, User $effector, $diff = [])
    {
        return $this->buildMessage($model, self::TRIGGER_UPDATED);
    }

    /**
     * @param string $label
     *
     * @return array
     */
    public function retrieveCallbacks($label)
    {
        return [
            [$this, "notify" . ucfirst($label)],
        ];
    }

    /**
     * @param PastoralMapper $pastoralMapper
     *
     * @return self|$this
     */
    public function setPastoralMapper(PastoralMapper $pastoralMapper)
    {
        $this->pastoralMapper = $pastoralMapper;

        return $this;
    }

    /**
     * @param PastoralAccessMapper $pastoralAccessMapper
     *
     * @return self|$this
     */
    public function setPastoralAccessMapper(PastoralAccessMapper $pastoralAccessMapper)
    {
        $this->pastoralAccessMapper = $pastoralAccessMapper;

        return $this;
    }

    /**
     * @param PastoralGroupMapper $pastoralGroupMapper
     *
     * @return self|$this
     */
    public function setPastoralGroupMapper(PastoralGroupMapper $pastoralGroupMapper)
    {
        $this->pastoralGroupMapper = $pastoralGroupMapper;

        return $this;
    }

    /**
     * @param RoleMapper $roleMapper
     *
     * @return self|$this
     */
    public function setRoleMapper(RoleMapper $roleMapper)
    {
        $this->roleMapper = $roleMapper;

        return $this;
    }

    /**
     * @param UserMapper $userMapper
     *
     * @return self|$this
     */
    public function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;

        return $this;
    }
}
