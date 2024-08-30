<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Alaress\Async\Notification\Parameter\Param;
use Psr\Container\ContainerInterface;
use Schoolbox\Learning\Moment\Evidence\Model as Evidence;
use Alaress\Async\Notification\Instance\Builder;
use Monolog\Logger;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\MapperFactory;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\ChildParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Routing\Filter\RoletypeFilter;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Evidence buildPrevious($current, ?array $diff, int $previousKey = 1)
 */
class Notifier extends AbstractModuleNotifier
{
    public const TRIGGER_POSTED = 'posted';

    private const PATTERN_SOMEONE_POSTED_TITLE = ':user posted :evidence for :student';
    private const PATTERN_STUDENT_POSTED_TITLE = ':student has posted :evidence';

    private const PATTERN_EVIDENCE_HREF = '/learning/evidence/%d';

    /** @var UserMapper */
    private UserMapper $userMapper;

    /**
     * @inheritdoc
     */
    public static function getSetters(ContainerInterface $di): array
    {
        /** @var MapperFactory $mf */
        $mf = $di->get('mapperFactory');

        return [
            'setMapper'     => $mf->get(Mapper::class),
            'setUserMapper' => $mf->get(UserMapper::class),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getSlug(): string
    {
        return NSMapper::NS_EVIDENCE;
    }

    /**
     * Setter for UserMapper
     * @param UserMapper $userMapper
     */
    public function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Attaches and detaches triggers properly depending on the Evidence status and transition
     *
     * @param TriggerBundleInterface $bundle
     * @return void
     */
    public function bundleTriggers($bundle): void
    {
        $crudMethod = $bundle->getMethod();

        /**
         * Deleted
         */
        if ($crudMethod === \Schoolbox\Mapper::METHOD_DELETE) {
            $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
            $bundle->detach(self::TRIGGER_POSTED);
            return;
        }

        /**
         * Created
         */
        if ($crudMethod === \Schoolbox\Mapper::METHOD_CREATE) {
            $bundle->attach(self::TRIGGER_POSTED, self::IMMEDIATELY);
        }
    }

    /**
     * Retracts a deleted Evidence.
     *
     * @param Evidence $evidence
     * @return Envelope
     */
    public function notifySystemDeleted(Evidence $evidence): Envelope
    {
        $envelope = new Envelope();
        return $this->retractPosted($envelope, $evidence);
    }

    /**
     * Retracts a given Evidence.
     *
     * @param Envelope $envelope
     * @param Evidence $evidence
     * @return Envelope
     */
    private function retractPosted(Envelope $envelope, Evidence $evidence): Envelope
    {
        return $this->retractEventObject($envelope, $evidence);
    }

    /**
     * Notify relevant users in given role type that an evidence has been posted
     *
     * @param string     $roleType
     * @param Evidence   $model
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    private function notifyPosted(string $roleType, Evidence $model, User $effector, ?array $diff): Envelope
    {
        $envelope = new Envelope();

        try {
            /** @var Evidence $evidence */
            $evidence = $this->mapper->findById($model->getId());
            $effector = $this->userMapper->findById($effector->getId());
            // hydrate to get teacher collection properly
            $student = $this->userMapper->findById($evidence->getRecipient()->getId());
        } catch (MapperException $e) {
            return $envelope;
        }

        $evidenceHref = sprintf(self::PATTERN_EVIDENCE_HREF, $evidence->getId());

        $bob = new Builder();
        $bob->setIcon($this->getUserIcon($effector))
            ->setHref($evidenceHref)
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addTopic("{$this->getSlug()}.{$evidence->getId()}." . self::TRIGGER_POSTED)
            ->setTimestamp($evidence->getCreatedAt()->getUnixTimeStamp())
            ->setStudentExternalIdMeta($evidence->getRecipient()->getExternalId())
            ->addFilter(RoletypeFilter::NAME, [$roleType]);

        $params = ['evidence' => new Param('a new learning moment', $evidenceHref, Param::STACKING_REPLACE_NO_DECORATION)];

        // a teacher or a parent posted for a student - notify the student, the teachers of the student and parents of the student
        if ($effector->getRole()->isStaff() || $effector->getRole()->isParent()) {
            $bob->setTitle(self::PATTERN_SOMEONE_POSTED_TITLE);
            $params['user'] = new UserParam($effector);
            // set student param
            $params['student'] = $roleType === Role::STUDENT ?
                new Param('you', null, Param::STACKING_REPLACE_NO_DECORATION) :
                ($roleType === Role::PARENT ? new ChildParam($student) : new UserParam($student));
        } elseif ($effector->getRole()->isStudent() && $student->getId() === $effector->getId()) {
            // a student posted - notify parents and teachers of the student
            $bob->setTitle(self::PATTERN_STUDENT_POSTED_TITLE);
            // set student param
            $params['student'] = $roleType === Role::PARENT ? new ChildParam($effector) : new UserParam($effector);
        } else {
            // should not happen
            return new Envelope();
        }
        $bob->setParams($params);
        $instance = $bob->build();
        // This is just to reduce work load of scheduled and routed.
        switch ($roleType) {
            case Role::STAFF:
                (new \Schoolbox\External\User())->loadTeachers([$student]);
                /** @var User $teacher */
                foreach ($student->getTeacherCollection() as $teacher) {
                    if (!$teacher->getRole()->getAccessEvidence()) {
                        continue;
                    }
                    $envelope->targetToUser($teacher, $instance);
                }
                break;
            case Role::STUDENT:
                $envelope->targetToUser($student, $instance);
                break;
            case Role::PARENT:
                $envelope->targetToParent($student->getId(), $instance);
                break;
            default:
                // should not happen
                return new Envelope();
        }
        return $envelope;
    }

    /**
     * Notify students that evidence has been posted.
     *
     * @param Evidence   $evidence
     * @param User       $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentPosted(Evidence $evidence, User $user, ?array $diff): Envelope
    {
        return $this->notifyPosted(Role::STUDENT, $evidence, $user, $diff);
    }

    /**
     * Notify staff that evidence has been posted.
     *
     * @param Evidence   $evidence
     * @param User       $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffPosted(Evidence $evidence, User $user, ?array $diff): Envelope
    {
        return $this->notifyPosted(Role::STAFF, $evidence, $user, $diff);
    }

    /**
     * Notify parents that evidence has been posted.
     *
     * @param Evidence   $evidence
     * @param User       $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentPosted(Evidence $evidence, User $user, ?array $diff): Envelope
    {
        return $this->notifyPosted(Role::PARENT, $evidence, $user, $diff);
    }
}
