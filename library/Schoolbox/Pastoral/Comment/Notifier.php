<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Pastoral\Comment;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\AccessLevelCensor;
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

    /** @var PastoralMapper $pastoralMapper */
    protected $pastoralMapper;

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

        if (empty($dependencies["l10n"])) {
            $stoneFactory = new RosettaStoneFactory();
            $dependencies["l10n"] = $stoneFactory->build(L10nMapper::getInstance());
        }

        parent::loadDependencies($dependencies);
    }

    /**
     * @param Model $model
     * @param       $method
     *
     * @return Envelope
     */
    protected function buildMessage(Model $model, string $method): Envelope
    {
        $envelope = new Envelope();

        // Get the pastoral record the comment relates to

        /** @var Pastoral $pastoral */
        try {
            $pastoral = $this->pastoralMapper->findById($model->getPastoral()->getId());
            $owner    = $this->userMapper->findById($model->getOwner()->getId());
        } catch (MapperException $e) {
            $this->log->error("Error: Unable to send pastoral comment {$method} notification. {$e->getMessage()}", ['exception' => $e]);
            return $envelope;
        }

        // Create the message
        $timestamp = md5(date("YmdHis"));
        $href = "/pastoral/student/{$pastoral->getStudent()->getId()}/record/{$pastoral->getId()}?ts={$timestamp}";

        $builder = new Builder();
        $builder->setTitle("A :pastoralcare comment has been {$method}")
            ->setParam('pastoralcare', new Param($this->l10n->translate('pastoralCare'), $href))
            ->setIcon("/portrait.php?size=square32&id={$owner->getId()}")
            ->setHref($href)
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::ERROR)
            ->addTopic("pastoral_comment.{$model->getId()}.{$method}");

        /** @var Instance $message */
        $message = $builder->build();

        // Work out who to send the message to

        /** @var Collection|PastoralAccess[] $accessRecords */
        $accessRecords = $this->pastoralAccessMapper->findByColumn("pastoral_id", $pastoral->getId());

        // Users to send notifications to. Starting with author of the record.
        $targetUsers[$pastoral->getOwner()->getId()] = $pastoral->getOwner();

        /** @var PastoralAccess $pastoralAccess */
        foreach ($accessRecords as $pastoralAccess) {
            // If the comment is private, and they don't have private, fail
            if ($pastoralAccess->getAccess() === PastoralAccessMapper::ACCESS_DENY ||
                ($model->getPrivate() && ($pastoralAccess->getAccess() < PastoralAccessMapper::ACCESS_PRIVATE))) {
                // do not send any notification
                continue;
            }

            if ($pastoralAccess->getRole() && $pastoralAccess->getRole()->isStaff()) {
                // Get the kids teachers
                try {
                    $student = $this->userMapper->findById($pastoral->getStudent()->getId());
                } catch (MapperException $e) {
                    // noop
                    $this->log->error("Error: Unable to send pastoral comment {$method} notification. {$e->getMessage()}", ['exception' => $e]);
                    continue;
                }
                $student->loadTeachers();
                $teachers = $student->getTeacherCollection();
                /** @var User $user */
                foreach ($teachers as $user) {
                    if ($user->getRole()->getId() === $pastoralAccess->getRole()->getId()) {
                        $targetUsers[$user->getId()] = $user;
                    }
                }
            } elseif ($pastoralAccess->getGroup()) {
                // Sending to a pastoral group
                try {
                    /** @var PastoralGroup $pastoralGroup */
                    $pastoralGroup = $this->pastoralGroupMapper->findById($pastoralAccess->getGroup()->getId());
                } catch (MapperException $e) {
                    // Log an error and move on so we don't break the rest of the notifications
                    $this->log->error("Error: Unable to send pastoral comment {$method} notification. {$e->getMessage()}", ['exception' => $e]);
                    continue;
                }

                /** @var PastoralGroupMember $member */
                foreach ($pastoralGroup->getMembers() as $member) {
                    // Only staff should be in pastoral groups
                    if ($member->getUser()->getRole()->isStaff()) {
                        $targetUsers[$member->getUser()->getId()] = $member->getUser();
                    }
                }
            } elseif ($pastoralAccess->getUser()) {
                // Only staff ever have access to pastoral comments
                if ($pastoralAccess->getUser()->getRole()->isStaff()) {
                    $targetUsers[$pastoralAccess->getUser()->getId()] = $pastoralAccess->getUser();
                }
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
    public function notifyCreated(Model $model, User $effector, $diff = []): Envelope
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
    public function notifyUpdated(Model $model, User $effector, $diff = []): Envelope
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

    /**
     * @return RosettaStone
     */
    public function getL10n(): RosettaStone
    {
        return $this->l10n;
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
