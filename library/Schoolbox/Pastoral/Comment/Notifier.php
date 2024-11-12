<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Comment;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\AccessLevelCensor;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\NotifierHelper;
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
        self::TRIGGER_CREATED         => "A :pastoralcare comment has been created",
        self::TRIGGER_CREATED_RELATED => "A :pastoralcare comment has been created",
        self::TRIGGER_UPDATED         => "A :pastoralcare comment has been updated",
        self::TRIGGER_UPDATED_RELATED => "A :pastoralcare comment has been updated",
    ];

    /** @var Mapper */
    protected $mapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected PastoralAccessMapper $pastoralAccessMapper;

    /** @var PastoralGroupMapper $pastoralGroupMapper */
    protected PastoralGroupMapper $pastoralGroupMapper;

    /** @var PastoralMapper $pastoralMapper */
    protected PastoralMapper $pastoralMapper;

    protected PastoralReportMapper $pastoralReportMapper;

    protected AccessLevelCensor $accessLevelCensor;

    /** @var RoleMapper $roleMapper */
    protected RoleMapper $roleMapper;

    /** @var UserMapper $userMapper */
    protected UserMapper $userMapper;

    /** @var RosettaStone $l10n */
    protected RosettaStone $l10n;

    /**
     * @inheritDoc
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

        if (empty($dependencies["l10n"])) {
            $stoneFactory         = new RosettaStoneFactory();
            $dependencies["l10n"] = $stoneFactory->build(L10nMapper::getInstance());
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

        // Get the pastoral record the comment relates to
        try {
            $pastoral = $this->pastoralMapper->findById($model->getPastoral()->getId());
            $owner    = $this->userMapper->findById($model->getOwner()->getId());
            $student  = $this->userMapper->findById($pastoral->getStudent()->getId());
            $this->externalUser->loadParents([$student]);
            $this->externalUser->loadTeachers([$student]);
            $pastoral->setStudent($student);
        } catch (MapperException $e) {
            $this->log->error("Error: Unable to send pastoral comment {$method} notification. {$e->getMessage()}", ['exception' => $e]);
            return $envelope;
        }

        // Create the message
        $href = NotifierHelper::getRecordHref($pastoral, $model);

        $builder = new Builder();
        $builder->setTitle(self::TITLE[$method])
                ->setParam('pastoralcare', new Param($this->l10n->translate('pastoralCare'), $href))
                ->setIcon($this->getUserIcon($owner))
                ->setHref($href)
                ->setNamespace($this->getSlug())
                ->setSeverity(Logger::ERROR)
                ->putMeta('type', $method) // to provide different key for $instance->getBaseHash() . $instance->getParamHash()
                ->addTopic(sprintf("pastoral_comment.%s.%s", $model->getId(), $method));

        $message = $builder->build();

        // Work out who to send the message to

        $parentAccessEnabled  = (bool)$this->conf->get(ConfigMapper::NAME_PASTORAL_PARENT_ACCESS_ENABLED);
        $studentAccessEnabled = (bool)$this->conf->get(ConfigMapper::NAME_PASTORAL_STUDENT_ACCESS_ENABLED);

        /** @var Collection|PastoralAccess[] $accessRecords */
        $accessRecords = $this->pastoralAccessMapper->findByColumn("pastoral_id", $pastoral->getId());
        $targetUsers   = (new NotifierHelper($this->userMapper, $this->pastoralGroupMapper, $this->accessLevelCensor, $this->log))
            ->buildTargetUsers(
                $accessRecords,
                $model->getPrivate(),
                $student,
                $parentAccessEnabled,
                $studentAccessEnabled,
                'pastoral comment',
                $method,
            );

        // Also send to owner of the record
        $targetUsers[$pastoral->getOwner()->getId()] = $pastoral->getOwner();

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
                $bundle->attach(self::TRIGGER_CREATED_RELATED, self::IMMEDIATELY);
                $bundle->attach(self::TRIGGER_ADDED_TO_REPORT, self::IMMEDIATELY);
                break;
//            case BaseMapper::METHOD_UPDATE:
//                $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
//                $bundle->attach(self::TRIGGER_UPDATED_RELATED, self::IMMEDIATELY);
//                break;
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
    public function notifyCreatedRelated(Model $model, User $effector, $diff = []): Envelope
    {
        return $this->buildMessage($model, self::TRIGGER_CREATED_RELATED);
    }

    /**
     * @deprecated Pastoral comments may not be modified once created, thus they
     *             have no update notifications.
     *             They may be deleted, but there is currently no source set up
     *             for deleted comments.
     *
     * @param Model $model
     * @param User  $effector
     * @param array $diff
     *
     * @return Envelope
     */
    public function notifyUpdated(Model $model, User $effector, $diff = []): Envelope
    {
        trigger_error(
            'Pastoral comments should never be updated, thus update notifications should never be raised',
            E_USER_DEPRECATED,
        );
        return new Envelope();
//        return $this->buildMessage($model, self::TRIGGER_UPDATED);
    }

    /**
     * @deprecated Pastoral comments may not be modified once created, thus they
     *             have no update notifications.
     *             They may be deleted, but there is currently no source set up
     *             for deleted comments.
     *
     * @param Model $model
     * @param User  $effector
     * @param array $diff
     *
     * @return Envelope
     */
    public function notifyUpdatedRelated(Model $model, User $effector, $diff = []): Envelope
    {
        trigger_error(
            'Pastoral comments should never be updated, thus update notifications should never be raised',
            E_USER_DEPRECATED,
        );
        return new Envelope();
//        return $this->buildMessage($model, self::TRIGGER_UPDATED_RELATED);
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
            $model    = $this->hydrate($model);
            $pastoral = $model->getPastoral();
        } catch (MapperException $e) {
            $this->log->error("Error: Unable to send pastoral comment addedToReport notification. {$e->getMessage()}", ['exception' => $e]);
            return $envelope;
        }

        // Create the message
        $recordHref   = NotifierHelper::getRecordHref($pastoral, $model);
        $recordName   = $this->l10n->translate('pastoral') . ' record';
        $messageTitle = "A new comment has been added to :record in :report";

        $reports = $this->pastoralReportMapper->findForRecord($pastoral, true);
        $this->log->debug(count($reports) . " reports found for pastoral {$pastoral->getId()}");
        $this->log->debug("Report ids: " . implode(',', $reports->getPropertyValues('id')));

        /** @var Report $report */
        foreach ($reports as $report) {
            $reportHref = NotifierHelper::getReportHref($report, $pastoral);
            $builder    = new Builder();
            $builder->setTitle($messageTitle)
                    ->setParam('record', new Param($recordName, $recordHref, ParameterInterface::STACKING_REPLACE))
                    ->setParam('report', new Param($report->getName(), $reportHref, ParameterInterface::STACKING_REPLACE))
                    ->setIcon($this->getUserIcon($effector))
                    ->setHref($reportHref)
                    ->setNamespace($this->getSlug())
                    ->setSeverity(Logger::ERROR)
                    ->setTimestamp($model->getUpdatedAt()->getUnixTimeStamp())
                    ->addTopic(sprintf("pastoral_comment.%s.%s.%s.%s", $model->getId(), $pastoral->getId(), $report->getId(), self::TRIGGER_ADDED_TO_REPORT));

            $message     = $builder->build();
            $targetUsers = (new NotifierHelper($this->userMapper, $this->pastoralGroupMapper, $this->accessLevelCensor, $this->log))
                ->findUsersForRecordAndReport($pastoral, $report, $model->getPrivate() ? PastoralAccessMapper::ACCESS_PRIVATE : PastoralAccessMapper::ACCESS_PUBLIC);
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

    /**
     * @param Model $model
     * @return Model
     * @throws MapperException
     */
    protected function hydrate($model)
    {
        $pastoral = $this->pastoralMapper->findById($model->getPastoral()->getId());
        $student  = $this->userMapper->findById($pastoral->getStudent()->getId());
        /* Load relationships into $student,
         * for `\Schoolbox\Pastoral\NotifierHelper::buildTargetUsersForRole`
         * which is called by `\Schoolbox\Pastoral\NotifierHelper::buildTargetUsers`
         * as that requires these to be loaded for `$student->getParents()` and `$student->getTeacherCollection()`
         */
        $this->externalUser->loadParents([$student]);
        $this->externalUser->loadTeachers([$student]);
        $pastoral->setStudent($student);
        $model->setPastoral($pastoral);
        return $model;
    }
}
