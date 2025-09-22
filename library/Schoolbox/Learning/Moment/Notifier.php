<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment;

use Alaress\Async\Notification\Parameter\Param;
use Psr\Container\ContainerInterface;
use Schoolbox\Learning\Moment\Model as Moment;
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
 * @method Moment buildPrevious($current, ?array $diff, int $previousKey = 1)
 */
class Notifier extends AbstractModuleNotifier
{
    public const TRIGGER_POSTED = 'posted';

    /* This is when a learning moment requires approval
     */
    public const TRIGGER_SUBMITTED = 'submitted';

    private const PATTERN_SOMEONE_POSTED_TITLE = ':user posted :moment for :student';
    private const PATTERN_STUDENT_POSTED_TITLE = ':student has posted :moment';
    private const PATTERN_SUBMITTED_TITLE = ':student posted :moment that requires approval';

    private const PATTERN_MOMENT_HREF = '/learning/evidence/%d';

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
     *
     * @param UserMapper $userMapper
     */
    public function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Attaches and detaches triggers properly depending on the learning moment status and transition
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

            /* A learning moment can be created immediately into the Submitted For Approval state
             */
            $bundle->attach(self::TRIGGER_SUBMITTED, self::IMMEDIATELY);
        }

        /* When a learning moment gets updated to then be in Submitted For Approval
         */
        if (\Schoolbox\Mapper::METHOD_UPDATE === $crudMethod) {
            $bundle->attach(self::TRIGGER_SUBMITTED, self::IMMEDIATELY);
        }
    }

    /**
     * Retracts a deleted learning moment.
     *
     * @param Moment $moment
     * @return Envelope
     */
    public function notifySystemDeleted(Moment $moment): Envelope
    {
        $envelope = new Envelope();
        return $this->retractPosted($envelope, $moment);
    }

    /**
     * Retracts a given learning moment.
     *
     * @param Envelope $envelope
     * @param Moment $moment
     * @return Envelope
     */
    private function retractPosted(Envelope $envelope, Moment $moment): Envelope
    {
        return $this->retractEventObject($envelope, $moment);
    }

    /**
     * Notify relevant users in given role type that a learning moment has been posted
     *
     * @param string $roleType
     * @param Moment $model
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     */
    private function notifyPosted(string $roleType, Moment $model, User $effector, ?array $diff): Envelope
    {
        $envelope = new Envelope();

        /* TODO where does everything go?
         */
        (new \Alaress_Log_File('/tmp/notifySubmitted.log'))->log('notifyPosted method', $roleType);

        // if the learning moment is draft (now draft, may have just been created, may have been moved to draft, do not notify
        if ($model->isDraft()) {
            return $envelope;
        }

        try {
            /** @var Moment $moment */
            $moment = $this->mapper->findById($model->getId());
            $effector = $this->userMapper->findById($effector->getId());
            // hydrate to get teacher collection properly
            $student = $this->userMapper->findById($moment->getRecipient()->getId());
        } catch (MapperException $e) {
            return $envelope;
        }

        $momentHref = sprintf(self::PATTERN_MOMENT_HREF, $moment->getId());

        $bob = new Builder();
        $bob->setIcon($this->getUserIcon($effector))
            ->setHref($momentHref)
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addTopic("{$this->getSlug()}.{$moment->getId()}." . self::TRIGGER_POSTED)
            ->setTimestamp($moment->getCreatedAt()->getUnixTimeStamp())
            ->setStudentExternalIdMeta($moment->getRecipient()->getExternalId())
            ->addFilter(RoletypeFilter::NAME, [$roleType]);

        $params = [
            'moment' => new Param('a new learning moment', $momentHref, Param::STACKING_REPLACE_NO_DECORATION)
        ];

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
     * Notify relevant users in given role type that a learning moment was put into Submitted For Approval
     * Note that users need to have the Learning Moment role permission to receive this notif
     *
     * @param string $roleType
     * @param Model $model
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     */
    private function notifySubmitted(string $roleType, Moment $model, User $effector, ?array $diff): Envelope
    {
        $envelope = new Envelope();

        /* TODO where does everything go? This never fires.
         */
        (new \Alaress_Log_File('/tmp/notifySubmitted.log'))->log('notifySubmitted method', $roleType);

        /* A necessary precondition
         */
        if (!$model->isSubmitted()) {
            return $envelope;
        }

        try {
            $effector = $this->userMapper->findById($effector->getId());

            /* Only students cause this notif: parents skip the moderation process along with staffs
             */
            if (!$effector->getRole()->isStudent()) {
                return $envelope;
            }

            /** @var Moment $moment */
            $moment = $this->mapper->findById($model->getId());
            // hydrate to get teacher collection properly
            $student = $this->userMapper->findById($moment->getRecipient()->getId());
        } catch (MapperException $e) {
            return $envelope;
        }

        /* This only matters when students post for themselves. I'm not sure if they could ever
         * get into a place where $effector != $student, but as  precaution:
         */
        if ($effector->getId() !== $student->getId()) {
            return $envelope;
        }

        /* Maybe go to its modify screen instead of this random detail screen?
         * Or maybe even the cohort report
         */
        $momentHref = sprintf(self::PATTERN_MOMENT_HREF, $moment->getId());

        $bob = (new Builder())
            ->setIcon($this->getUserIcon($effector))
            ->setHref($momentHref)
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addTopic("{$this->getSlug()}.{$moment->getId()}." . self::TRIGGER_SUBMITTED)
            ->setTimestamp($moment->getCreatedAt()->getUnixTimeStamp())
            ->setStudentExternalIdMeta($moment->getRecipient()->getExternalId())
            ->addFilter(RoletypeFilter::NAME, [$roleType]);

        $params = [
            'moment' => new Param('a learning moment', $momentHref, Param::STACKING_REPLACE_NO_DECORATION)
        ];

        /* At this point:
         *     - student is the effector and posted the learning moment for themselves
         *     - the learning moment is in Submitted For Approval
         *
         * Now we can tell the staff about it
         */
        $bob->setTitle(self::PATTERN_SUBMITTED_TITLE);

        /* Sub in the student's name
         */
        $params['student'] = new UserParam($effector);

        $bob->setParams($params);

        $instance = $bob->build();

        /* Tell $effector's teachers that they've submitted a learning moment for approval
         * Any teachers with the Learning Moment role permission will get a notif
         */
        (new \Schoolbox\External\User())->loadTeachers([$student]);

        /** @var User $teacher */
        foreach ($student->getTeacherCollection() as $teacher) {
            /* Skip teachers who don't have the learning moment role permission
             */
            if (!$teacher->getRole()->getAccessEvidence()) {
                continue;
            }

            $envelope->targetToUser($teacher, $instance);
        }

        return $envelope;
    }


    /**
     * Notify students that a learning moment has been posted.
     *
     * @param Moment $moment
     * @param User $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentPosted(Moment $moment, User $user, ?array $diff): Envelope
    {
        return $this->notifyPosted(Role::STUDENT, $moment, $user, $diff);
    }

    /**
     * Notify staff that a learning moment has been posted.
     *
     * @param Moment $moment
     * @param User $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffPosted(Moment $moment, User $user, ?array $diff): Envelope
    {
        return $this->notifyPosted(Role::STAFF, $moment, $user, $diff);
    }

    /**
     * Notify parents that a learning moment has been posted.
     *
     * @param Moment $moment
     * @param User $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentPosted(Moment $moment, User $user, ?array $diff): Envelope
    {
        return $this->notifyPosted(Role::PARENT, $moment, $user, $diff);
    }


    /**
     * Notify a staff member that a learning moment was submitted for approval
     *
     * @param Model $moment
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffSubmitted(Moment $moment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifySubmitted(Role::STAFF, $moment, $effector, $diff);
    }
}
