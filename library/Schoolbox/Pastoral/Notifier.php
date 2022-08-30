<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
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
    const TRIGGER_CREATED         = 'created';
    const TRIGGER_UPDATED         = 'updated';
    const TRIGGER_ADDED_TO_REPORT = 'addedToReport';

    // fields to notify when the value is changed
    private const COLS = [
        'type',
        'subtype',
        'severity',
        'heading',
        'bodyPrivate',
        'bodyPublic',
        'points',
        'attachmentsPrivate',
        'attachmentsPublic',
        'tags',
    ];

    /** @var Mapper */
    protected $mapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected PastoralAccessMapper $pastoralAccessMapper;

    /** @var PastoralGroupMapper $pastoralGroupMapper */
    protected PastoralGroupMapper $pastoralGroupMapper;

    /** @var RoleMapper $roleMapper */
    protected RoleMapper $roleMapper;

    /** @var UserMapper $userMapper */
    protected UserMapper $userMapper;

    protected PastoralReportMapper $pastoralReportMapper;

    /** @var RosettaStone $l10n */
    protected RosettaStone $l10n;

    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies["l10n"])) {
            $stoneFactory         = new RosettaStoneFactory();
            $dependencies["l10n"] = $stoneFactory->build(L10nMapper::getInstance());
        }

        if (empty($dependencies["pastoralAccessMapper"])) {
            $dependencies["pastoralAccessMapper"] = PastoralAccessMapper::getInstance();
        }

        if (empty($dependencies["pastoralGroupMapper"])) {
            $dependencies["pastoralGroupMapper"] = PastoralGroupMapper::getInstance();
        }

        if (empty($dependencies["pastoralReportMapper"])) {
            $dependencies["pastoralReportMapper"] = PastoralReportMapper::getInstance();
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
     * @param Model      $model
     * @param string     $method
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    protected function buildMessage(Model $model, string $method, User $effector, ?array $diff): Envelope
    {
        $envelope = new Envelope();

        // Get hydrated pastoral record
        try {
            $pastoral = $this->mapper->findById($model->getId());
            $student  = $this->userMapper->findById($pastoral->getStudent()->getId());
            $student->loadParents();
            $student->loadTeachers();
            $pastoral->setStudent($student);
        } catch (MapperException $e) {
            $this->log->error("Error: Unable to send pastoral comment {$method} notification. {$e->getMessage()}", ['exception' => $e]);
            return $envelope;
        }

        // Get the kids teachers
        // Create the message
        $href     = "/pastoral/student/{$pastoral->getStudent()->getId()}/record/{$pastoral->getId()}";
        $severity = $pastoral->getSeverity()
            ? sprintf('%s %s', strtolower($pastoral->getSeverity()->getName()), $this->l10n->translate('severity'))
            : '';
        $type     = strtolower($pastoral->getSubType() instanceof SubType\Model ? $pastoral->getSubType()->getName() : $pastoral->getType()->getName());

        $builder = new Builder();
        $builder->setTitle("A :pastoralcare record has been {$method}")
                ->setParam('pastoralcare', new Param("{$severity} {$type}", $href, ParameterInterface::STACKING_APPEND))
                ->setIcon($this->getUserIcon($effector))
                ->setHref($href)
                ->setNamespace($this->getSlug())
                ->setSeverity(Logger::ERROR)
                ->setTimestamp($pastoral->getUpdatedAt()->getUnixTimeStamp())
                ->addTopic("pastoral.{$pastoral->getId()}.{$method}");

        $message = $builder->build();

        // Work out who to send the message to

        $parentAccessEnabled  = (bool)$this->conf->get(ConfigMapper::NAME_PASTORAL_PARENT_ACCESS_ENABLED);
        $studentAccessEnabled = (bool)$this->conf->get(ConfigMapper::NAME_PASTORAL_STUDENT_ACCESS_ENABLED);

        $privateChangeOnly = false;
        if ($diff) {
            $relevantDiff      = array_intersect(array_keys($diff), self::COLS);
            $privateChangeOnly = empty(array_diff($relevantDiff, ['bodyPrivate', 'attachmentsPrivate']));
        }

        /** @var Collection|PastoralAccess[] $accessRecords */
        $accessRecords = $this->pastoralAccessMapper->findByColumn("pastoral_id", $pastoral->getId());
        $targetUsers   = (new NotifierHelper($this->userMapper, $this->pastoralGroupMapper, $this->log))
            ->buildTargetUsers(
                $accessRecords,
                $privateChangeOnly,
                $student,
                $parentAccessEnabled,
                $studentAccessEnabled,
                'pastoral',
                $method,
            );


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
        $diff = $bundle->getEvent()->getDiff();

        if ($diff && count(array_intersect(array_keys($diff), self::COLS)) === 0) {
            return;
        }

        $now = time() + 5;

        switch ($bundle->getMethod()) {
            case BaseMapper::METHOD_CREATE:
                $bundle->attach(self::TRIGGER_CREATED, $now);
                $bundle->attach(self::TRIGGER_ADDED_TO_REPORT, $now);
                break;
            case BaseMapper::METHOD_UPDATE:
                $bundle->attach(self::TRIGGER_UPDATED, $now);
                break;
        }
    }

    /**
     * @param Model      $model
     * @param User       $effector
     * @param array|null $diff
     *
     * @return Envelope
     */
    public function notifyCreated(Model $model, User $effector, $diff = []): Envelope
    {
        return $this->buildMessage($model, self::TRIGGER_CREATED, $effector, $diff);
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
        return $this->buildMessage($model, self::TRIGGER_UPDATED, $effector, $diff);
    }

    /**
     * @param Model $model
     * @param User  $effector
     * @param       $diff
     * @return Envelope
     */
    public function notifyStaffAddedToReport(Model $model, User $effector, $diff = []): Envelope
    {
        $envelope = new Envelope();

        // Get hydrated pastoral record
        try {
            $pastoral = $this->mapper->findById($model->getId());
            $student  = $this->userMapper->findById($pastoral->getStudent()->getId());
//            $student->loadParents();
            $student->loadTeachers();
            $pastoral->setStudent($student);
        } catch (MapperException $e) {
            $this->log->error("Error: Unable to send pastoral addedToReport notification. {$e->getMessage()}", ['exception' => $e]);
            return $envelope;
        }

        // Get the kids teachers
        // Create the message
        $href     = "/pastoral/student/{$pastoral->getStudent()->getId()}/record/{$pastoral->getId()}";
        $severity = $pastoral->getSeverity()
            ? sprintf('%s %s', strtolower($pastoral->getSeverity()->getName()), $this->l10n->translate('severity'))
            : '';
        $type     = strtolower($pastoral->getSubType() instanceof SubType\Model ? $pastoral->getSubType()->getName() : $pastoral->getType()->getName());

        $builder = new Builder();
        $builder->setTitle("A new :pastoralcare record is added to your :report")
                ->setParam('pastoralcare', new Param("{$severity} {$type}", $href, ParameterInterface::STACKING_APPEND))
                ->setIcon($this->getUserIcon($effector))
                ->setHref($href)
                ->setNamespace($this->getSlug())
                ->setSeverity(Logger::ERROR)
                ->setTimestamp($pastoral->getUpdatedAt()->getUnixTimeStamp())
                ->addTopic("pastoral.{$pastoral->getId()}." . self::TRIGGER_ADDED_TO_REPORT);

        $message = $builder->build();

        $reports = $this->findReportForRecord($pastoral);

        $targetUsers = $this->findUsersForReports($reports);



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
     * @return static
     */
    public function setPastoralAccessMapper(PastoralAccessMapper $pastoralAccessMapper): self
    {
        $this->pastoralAccessMapper = $pastoralAccessMapper;

        return $this;
    }

    /**
     * @param PastoralGroupMapper $pastoralGroupMapper
     *
     * @return static
     */
    public function setPastoralGroupMapper(PastoralGroupMapper $pastoralGroupMapper): self
    {
        $this->pastoralGroupMapper = $pastoralGroupMapper;

        return $this;
    }

    /**
     * @param RoleMapper $roleMapper
     *
     * @return static
     */
    public function setRoleMapper(RoleMapper $roleMapper): self
    {
        $this->roleMapper = $roleMapper;

        return $this;
    }

    /**
     * @param UserMapper $userMapper
     *
     * @return static
     */
    public function setUserMapper(UserMapper $userMapper): self
    {
        $this->userMapper = $userMapper;

        return $this;
    }

    /**
     * @param RosettaStone $l10n
     * @return static
     */
    public function setL10n(RosettaStone $l10n): self
    {
        $this->l10n = $l10n;

        return $this;
    }

    private function findReportForRecord(Model $pastoral)
    {
        $this->pastoralReportMapper->findForRecord();
    }

    private function findUsersForReports(Collection $reports)
    {

    }


}
