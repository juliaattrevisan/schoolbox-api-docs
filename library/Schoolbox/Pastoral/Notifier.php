<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress_Date_NullDate;
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

    /** @var ConfigMapper $configMapper */
    protected $configMapper;

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
        if (empty($dependencies["configMapper"])) {
            $dependencies["configMapper"] = ConfigMapper::getInstance();
        }

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
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    protected function buildMessage($model, $method)
    {
        $envelope = new Envelope();

        // Get hydrated pastoral record

        $pastoral = $this->mapper->findById($model->getId());

        // Get the kids teachers

        $student = $this->userMapper->findById($model->getStudent()->getId());
        $student->loadTeachers();
        $student->loadParents();
        $teachers = $student->getTeacherCollection();

        // Create the message

        $timestamp = md5(date("YmdHis"));

        $new = $method == self::TRIGGER_CREATED ? "new " : "";
        $href = "/pastoral/student/{$model->getStudent()->getId()}/record/{$model->getId()}?ts={$timestamp}";

        $builder = new Builder();
        $builder->setTitle("A {$new}:pastoralcare record has been {$method}")
                ->setParam('pastoralcare', new Param($this->l10n->translate('pastoralCare'), $href))
                ->setIcon("/portrait.php?size=square32&userId={$pastoral->getOwner()->getExternalId()}")
                ->setHref($href)
                ->setNamespace($this->getSlug())
                ->setSeverity(Logger::ERROR)
                ->addTopic("pastoral.{$model->getId()}.{$method}");

        $message = $builder->build();

        // Work out who to send the message to

        $parentAccessEnabled = $this->configMapper->get('pastoral_parent_access') == '1';

        /** @var Collection|PastoralAccess[] $accessRecords */
        $accessRecords = $this->pastoralAccessMapper->findByColumn("pastoral_id", $model->getId());

        /** @var PastoralAccess $pastoralAccess */
        foreach ($accessRecords as $pastoralAccess) {
            $role          = $pastoralAccess->getRole();
            $pastoralGroup = $pastoralAccess->getGroup();
            $user          = $pastoralAccess->getUser();

            try {
                if ($role instanceof Role) {
                    // Sending to a role, make sure its hydrated

                    $role = $this->roleMapper->findById($role->getId());

                    // The system should only notify staff if they are a teacher of the student

                    if ($role->isStaff()) {
                        /** @var User $teacher */
                        foreach ($teachers as $teacher) {
                            if ($teacher->getRole()->getId() == $role->getId()) {
                                $this->targetToUser($teacher, $message, $pastoralAccess, $envelope);
                            }
                        }
                    } elseif ($role->isParent() && $parentAccessEnabled) {
                        // Only send to parents if pastoral parent access is enabled

                        $envelope->targetToParent($model->getStudent()->getId(), $message);
                    }
                } elseif ($pastoralGroup instanceof PastoralGroup) {
                    // Sending to a pastoral group, make sure its hydrated

                    /** @var PastoralGroup $pastoralGroup */
                    $pastoralGroup = $this->pastoralGroupMapper->findById($pastoralGroup->getId());

                    /** @var PastoralGroupMember $member */
                    foreach ($pastoralGroup->getMembers() as $member) {
                        // Only staff should be in pastoral groups

                        // user is not hydrated
                        $user = $this->userMapper->findById($member->getUser()->getId());

                        if ($user->getRole()->isStaff()) {
                            $this->targetToUser($user, $message, $pastoralAccess, $envelope);
                        }
                    }
                } elseif ($user instanceof User) {
                    // Sending to an individual user

                    // user is not hydrated
                    $user = $this->userMapper->findById($user->getId());

                    $role = $user->getRole();

                    // Only send to parents if parent access is enabled

                    if ($role->isStaff() || ($role->isParent() && $parentAccessEnabled)) {
                        $this->targetToUser($user, $message, $pastoralAccess, $envelope);
                    }
                }
            } catch (MapperException $e) {
                // Log an error and move on so we don't break the rest of the notifications
                $this->log->error("Error: Unable to send pastoral {$method} notification. {$e->getMessage()}", [$e]);
            }
        }

        return $envelope;
    }

    /**
     * Only target message to the user if they have sufficient permissions
     *
     * @param User           $user
     * @param Instance       $message
     * @param PastoralAccess $pastoralAccess
     * @param Envelope       $envelope
     *
     * @return bool
     */
    private function targetToUser($user, $message, $pastoralAccess, $envelope)
    {
        // If user has deny permission, fail

        if ($pastoralAccess->getAccess() === PastoralAccessMapper::ACCESS_DENY) {
            return false;
        }

        // Otherwise it's bombs away...

        $envelope->targetToUser($user->getId(), $message);

        return true;
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
     * @throws MapperException
     * @throws MapperException\Deleted
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
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    public function notifyUpdated(Model $model, User $effector, $diff = [])
    {
        // Prevents update notification being sent on create due to external pastoral mapper adding the external ID

        if (!$model->getCreatedAt() instanceof Alaress_Date_NullDate && $model->getCreatedAt() !== null) {
            return $this->buildMessage($model, self::TRIGGER_UPDATED);
        }
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
     * @param ConfigMapper $configMapper
     *
     * @return self|$this
     */
    public function setConfigMapper(ConfigMapper $configMapper)
    {
        $this->configMapper = $configMapper;

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
