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
use Schoolbox\Pastoral\Report\Model as Report;
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
    // For staff, this will trigger for user / group based permissions, not for role based permissions
    private const TRIGGER_CREATED = 'created';
    private const TRIGGER_UPDATED = 'updated';
    // For staff, this will trigger and role based permissions, not for user / group based permissions
    public const  TRIGGER_CREATED_RELATED = 'createdRelated';
    public const  TRIGGER_UPDATED_RELATED = 'updatedRelated';
    private const TRIGGER_ADDED_TO_REPORT = 'addedToReport';

    // titles must not be the same
    private const TITLE = [
        self::TRIGGER_CREATED         => "A :pastoralcare record has been created",
        self::TRIGGER_CREATED_RELATED => "A :pastoralcare record has been created",
        self::TRIGGER_UPDATED         => "A :pastoralcare record has been updated",
        self::TRIGGER_UPDATED_RELATED => "A :pastoralcare record has been updated",
    ];

    // fields to notify when the value is changed
    private const COLS = [
        'type',
        'subtype',
        'severity',
        'heading',
        'bodyPrivate',
        'bodyPublic',
        'occurredOn',
        'points',
        'attachmentsPrivate',
        'attachmentsPublic',
        'tags',
        'access',
    ];

    /** @var Mapper */
    protected $mapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected PastoralAccessMapper $pastoralAccessMapper;

    /** @var PastoralGroupMapper $pastoralGroupMapper */
    protected PastoralGroupMapper $pastoralGroupMapper;

    protected PastoralReportMapper $pastoralReportMapper;

    protected AccessLevelCensor $accessLevelCensor;

    /** @var RoleMapper $roleMapper */
    protected RoleMapper $roleMapper;

    /** @var UserMapper $userMapper */
    protected UserMapper $userMapper;

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

        if (empty($dependencies["accessLevelCensor"])) {
            $dependencies["accessLevelCensor"] = new AccessLevelCensor(\Schoolbox\Pastoral\Group\Member\Mapper::getInstance());
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
            $pastoral = $this->hydrate($model);
        } catch (MapperException $e) {
            $this->log->error("Error: Unable to send pastoral {$method} notification. {$e->getMessage()}", ['exception' => $e]);
            return $envelope;
        }

        // Get the kids teachers
        // Create the message
        $href     = NotifierHelper::getRecordHref($pastoral, null);
        $severity = $pastoral->getSeverity()
            ? sprintf('%s %s', strtolower($pastoral->getSeverity()->getName()), $this->l10n->translate('severity'))
            : '';
        $type     = strtolower($pastoral->getType()->getName());
        if ($pastoral->getSubType() instanceof SubType\Model) {
            $type = sprintf('%s (%s)', $type, strtolower($pastoral->getSubType()->getName()));
        }

        $builder = new Builder();
        $builder->setTitle(self::TITLE[$method])
                ->setParam('pastoralcare', new Param("{$severity} {$type}", $href, ParameterInterface::STACKING_APPEND))
                ->setIcon($this->getUserIcon($effector))
                ->setHref($href)
                ->setNamespace($this->getSlug())
                ->setSeverity(Logger::ERROR)
                ->setTimestamp($pastoral->getUpdatedAt()->getUnixTimeStamp())
                ->putMeta('type', $method) // to provide different key for $instance->getBaseHash() . $instance->getParamHash()
                ->addTopic(sprintf("pastoral.%s.%s", $pastoral->getId(), $method))
                ->putMeta('studentExternalId', $pastoral->getStudent()->getExternalId());

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
        $targetUsers   = (new NotifierHelper($this->userMapper, $this->pastoralGroupMapper, $this->accessLevelCensor, $this->log))
            ->buildTargetUsers(
                $accessRecords,
                $privateChangeOnly,
                $pastoral->getStudent(),
                $parentAccessEnabled,
                $studentAccessEnabled,
                'pastoral',
                $method,
            );

        foreach ($targetUsers as $userId => $user) {
            // make sure that multiple conditions allow user to access this record
            if ($this->accessLevelCensor->getAccessLevel($pastoral, $user) === PastoralAccessMapper::ACCESS_DENY) {
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
                $bundle->attach(self::TRIGGER_CREATED_RELATED, $now);
                $bundle->attach(self::TRIGGER_ADDED_TO_REPORT, $now);
                break;
            case BaseMapper::METHOD_UPDATE:
                $bundle->attach(self::TRIGGER_UPDATED, $now);
                $bundle->attach(self::TRIGGER_UPDATED_RELATED, $now);
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
    public function notifyCreated(Model $model, User $effector, ?array $diff = []): Envelope
    {
        return $this->buildMessage($model, self::TRIGGER_CREATED, $effector, $diff);
    }

    /**
     * @param Model      $model
     * @param User       $effector
     * @param array|null $diff
     *
     * @return Envelope
     */
    public function notifyCreatedRelated(Model $model, User $effector, ?array $diff = []): Envelope
    {
        return $this->buildMessage($model, self::TRIGGER_CREATED_RELATED, $effector, $diff);
    }

    /**
     * @param Model      $model
     * @param User       $effector
     * @param array|null $diff
     *
     * @return Envelope
     */
    public function notifyUpdated(Model $model, User $effector, ?array $diff = []): Envelope
    {
        return $this->buildMessage($model, self::TRIGGER_UPDATED, $effector, $diff);
    }

    /**
     * @param Model      $model
     * @param User       $effector
     * @param array|null $diff
     *
     * @return Envelope
     */
    public function notifyUpdatedRelated(Model $model, User $effector, ?array $diff = []): Envelope
    {
        return $this->buildMessage($model, self::TRIGGER_UPDATED_RELATED, $effector, $diff);
    }

    /**
     * @param Model      $model
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyAddedToReport(Model $model, User $effector, ?array $diff = []): Envelope
    {
        $envelope = new Envelope();

        // Get hydrated pastoral record
        try {
            $pastoral = $this->hydrate($model);
        } catch (MapperException $e) {
            $this->log->error("Error: Unable to send pastoral addedToReport notification. {$e->getMessage()}", ['exception' => $e]);
            return $envelope;
        }

        // Create the message
        $recordHref   = NotifierHelper::getRecordHref($pastoral, null);
        $recordName   = $this->l10n->translate('pastoral') . ' record';
        $messageTitle = "A new :record has been added to :report";

        $reports = $this->pastoralReportMapper->findForRecord($pastoral, true);
        $this->log->debug(count($reports) . " reports found for pastoral {$pastoral->getId()}");
        $this->log->debug("Report ids: " . implode(',', $reports->getPropertyValues('id')));

        /** @var Report $report */
        foreach ($reports as $report) {
            $reportHref = NotifierHelper::getReportHref($report);
            $builder    = new Builder();
            $builder->setTitle($messageTitle)
                    ->setParam('record', new Param($recordName, $recordHref, ParameterInterface::STACKING_REPLACE))
                    ->setParam('report', new Param($report->getName(), $reportHref, ParameterInterface::STACKING_REPLACE))
                    ->setIcon($this->getUserIcon($effector))
                    ->setHref($reportHref)
                    ->setNamespace($this->getSlug())
                    ->setSeverity(Logger::ERROR)
                    ->setTimestamp($pastoral->getUpdatedAt()->getUnixTimeStamp())
                    ->addTopic(sprintf("pastoral.%s.%s.%s", $pastoral->getId(), $report->getId(), self::TRIGGER_ADDED_TO_REPORT))
                    ->putMeta('studentExternalId', $pastoral->getStudent()->getExternalId());

            $message     = $builder->build();
            $targetUsers = (new NotifierHelper($this->userMapper, $this->pastoralGroupMapper, $this->accessLevelCensor, $this->log))
                ->findUsersForRecordAndReport($pastoral, $report, PastoralAccessMapper::ACCESS_PUBLIC);
            foreach ($targetUsers as $userId => $user) {
                $envelope->targetToUser($userId, $message);
            }
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
     * @param PastoralReportMapper $pastoralReportMapper
     *
     * @return static
     */
    public function setPastoralReportMapper(PastoralReportMapper $pastoralReportMapper): self
    {
        $this->pastoralReportMapper = $pastoralReportMapper;

        return $this;
    }

    /**
     * @param AccessLevelCensor $accessLevelCensor
     *
     * @return self
     */
    public function setAccessLevelCensor(AccessLevelCensor $accessLevelCensor): self
    {
        $this->accessLevelCensor = $accessLevelCensor;

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

    /**
     * @param Model $model
     * @return object|Model
     * @throws MapperException
     */
    protected function hydrate($model)
    {
        $pastoral = $this->mapper->findById($model->getId());
        $student  = $this->userMapper->findById($pastoral->getStudent()->getId());
        /* Load relationships into $student,
         * for `\Schoolbox\Pastoral\NotifierHelper::buildTargetUsersForRole`
         * which is called by `\Schoolbox\Pastoral\NotifierHelper::buildTargetUsers`
         * as that requires these to be loaded for `$student->getParents()` and `$student->getTeacherCollection()`
         */
        $this->externalUser->loadParents([$student]);
        $this->externalUser->loadTeachers([$student]);
        $pastoral->setStudent($student);
        return $pastoral;
    }
}
