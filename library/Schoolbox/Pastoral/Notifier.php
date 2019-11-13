<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Pastoral;

use Alaress\Async\Notification\Instance\Builder;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\L10n\RosettaStoneFactory;
use Schoolbox\L10n\RosettaStone;
use Alaress\Async\Notification\Parameter\Param;

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

    /** @var RoleMapper $roleMapper */
    protected $roleMapper;

    /** @var UserMapper $userMapper */
    protected $userMapper;

    /** @var RosettaStone $l10n */
    protected $l10n;

    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies["l10n"])) {
            $stoneFactory = new RosettaStoneFactory();
            $dependencies["l10n"] = $stoneFactory->build(L10nMapper::getInstance());
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
    protected function buildMessage(Model $model, string $method): Envelope
    {
        $envelope = new Envelope();

        // Get hydrated pastoral record
        try {
            $pastoral = $this->mapper->findById($model->getId());
            $student  = $this->userMapper->findById($pastoral->getStudent()->getId());
            $pastoral->setStudent($student);
        } catch (MapperException $e) {
            $this->log->error("Error: Unable to send pastoral comment {$method} notification. {$e->getMessage()}", ['exception' => $e]);
            return $envelope;
        }

        // Get the kids teachers
        // Create the message

        $timestamp = md5(date("YmdHis"));

        $href = "/pastoral/student/{$pastoral->getStudent()->getId()}/record/{$pastoral->getId()}?ts={$timestamp}";
        $severity = $pastoral->getSeverity()
            ? sprintf('%s %s', strtolower($pastoral->getSeverity()->getName()), $this->l10n->translate('severity'))
            : '';
        $type = strtolower($pastoral->getSubType() instanceof SubType\Model ? $pastoral->getSubType()->getName() : $pastoral->getType()->getName());

        $builder = new Builder();
        $builder->setTitle("A :pastoralcare record has been {$method}")
                ->setParam('pastoralcare', new Param("{$severity} {$type}", $href))
                ->setIcon("/portrait.php?size=square32&id={$pastoral->getOwner()->getId()}")
                ->setHref($href)
                ->setNamespace($this->getSlug())
                ->setSeverity(Logger::ERROR)
                ->addTopic("pastoral.{$pastoral->getId()}.{$method}");

        $message = $builder->build();

        // Work out who to send the message to

        $parentAccessEnabled = $this->conf->get('pastoral_parent_access') == '1';

        /** @var Collection|PastoralAccess[] $accessRecords */
        $accessRecords = $this->pastoralAccessMapper->findByColumn("pastoral_id", $pastoral->getId());

        // Users to send notifications to. Starting with author of the record.
        $targetUsers = [];

        /** @var PastoralAccess $pastoralAccess */
        foreach ($accessRecords as $pastoralAccess) {
            // If the comment is private, and they don't have private, fail
            if ($pastoralAccess->getAccess() === PastoralAccessMapper::ACCESS_DENY) {
                // do not send any notification
                continue;
            }

            try {
                if ($pastoralAccess->getRole() instanceof Role) {
                    // The system should only notify staff if they are a teacher of the student
                    if ($pastoralAccess->getRole()->isStaff()) {
                        $student->loadTeachers();
                        $teachers = $student->getTeacherCollection();
                        /** @var User $teacher */
                        foreach ($teachers as $teacher) {
                            if ($teacher->getRole()->getId() === $pastoralAccess->getRole()->getId()) {
                                $targetUsers[$teacher->getId()] = $teacher;
                            }
                        }
                    } elseif ($pastoralAccess->getRole()->isParent() && $parentAccessEnabled) {
                        // Only send to parents if pastoral parent access is enabled
                        $student->loadParents();
                        $parents = $student->getParents();
                        foreach ($parents as $parent) {
                            if ($parent->getRole()->getId() === $pastoralAccess->getRole()->getId()) {
                                $targetUsers[$parent->getId()] = $parent;
                            }
                        }
                    }
                } elseif ($pastoralAccess->getGroup() instanceof PastoralGroup) {
                    // Sending to a pastoral group, make sure its hydrated
                    /** @var PastoralGroup $pastoralGroup */
                    $pastoralGroup = $this->pastoralGroupMapper->findById($pastoralAccess->getGroup()->getId());

                    /** @var PastoralGroupMember $member */
                    foreach ($pastoralGroup->getMembers() as $member) {
                        // Only staff should be in pastoral groups
                        $user = $this->userMapper->findById($member->getUser()->getId());
                        if ($user->getRole()->isStaff()) {
                            $targetUsers[$user->getId()] = $user;
                        }
                    }
                } elseif ($pastoralAccess->getUser() instanceof User) {
                    // Sending to an individual user
                    // user is not hydrated
                    $user = $this->userMapper->findById($pastoralAccess->getUser()->getId());
                    // Only send to parents if parent access is enabled
                    if ($user->getRole()->isStaff() || ($user->getRole()->isParent() && $parentAccessEnabled)) {
                        $targetUsers[$pastoralAccess->getUser()->getId()] = $pastoralAccess->getUser();
                    }
                }
            } catch (MapperException $e) {
                // Log an error and move on so we don't break the rest of the notifications
                $this->log->error("Error: Unable to send pastoral {$method} notification. {$e->getMessage()}", ['exception' => $e]);
            }
        }

        $accessLevelCensor = new AccessLevelCensor();
        foreach ($targetUsers as $userId => $user) {
            // make sure that multiple conditions allow user to access this record
            if ($accessLevelCensor->getAccessLevel($pastoral, $user) === PastoralAccessMapper::ACCESS_DENY) {
                continue;
            }
            $envelope->targetToUser($userId, $message);
        }

        return $envelope;
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
        $now = time() + 5;

        switch ($bundle->getMethod()) {
            case BaseMapper::METHOD_CREATE:
                $bundle->attach(self::TRIGGER_CREATED, $now);
                break;
            case BaseMapper::METHOD_UPDATE:
                $bundle->attach(self::TRIGGER_UPDATED, $now);
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

    /**
     * @param RosettaStone $l10n
     * @return self|$this
     */
    public function setL10n(RosettaStone $l10n)
    {
        $this->l10n = $l10n;

        return $this;
    }
}
