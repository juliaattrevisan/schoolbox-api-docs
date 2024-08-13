<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award;

use Alaress\Async\Notification\Parameter\Param;
use Psr\Container\ContainerInterface;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Badge\Award\Model as Award;
use Alaress\Async\Notification\Instance\Builder;
use Monolog\Logger;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\MapperFactory;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\SimpleParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Routing\Filter\RoletypeFilter;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Award buildPrevious($current, ?array $diff, int $previousKey = 1)
 */
class Notifier extends AbstractModuleNotifier
{
    public const TRIGGER_GOAL_SET = 'goalSet';
    public const TRIGGER_AWARDED  = 'awarded';

    private const PATTERN_STAFF_GOAL_SET_TITLE        = ':teacher has set :badge as a goal for :student';
    private const PATTERN_STUDENT_GOAL_SET_TITLE      = ':student has set :badge as a goal';
    private const PATTERN_STAFF_AWARDED_TITLE_STUDENT = ':teacher has awarded you :badge, :message!';
    private const PATTERN_STAFF_AWARDED_TITLE         = ':teacher has awarded :badge to :student';
    private const PATTERN_STUDENT_AWARDED_TITLE       = ':student has achieved :badge';

    private const PATTERN_AWARDED_GOAL_SET_HREF = '/learning/badge/award/%d/viewEvidence';

    private const MESSAGES = [
        'well done',
        'congratulations',
        'excellent work',
    ];

    /**
     * @var UserMapper
     */
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
        return NSMapper::NS_BADGE;
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
     * Attaches and detaches triggers properly depending on the Award status and transition
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
            $bundle->detach(self::TRIGGER_GOAL_SET);
            $bundle->detach(self::TRIGGER_AWARDED);
            return;
        }


        /** @var Award $badge */
        $badge = $bundle->getEvent()->getObject();

        /**
         * Created
         */
        if ($crudMethod === \Schoolbox\Mapper::METHOD_CREATE) {
            if ($badge->isAchievement()) {
                $bundle->attach(self::TRIGGER_AWARDED, self::IMMEDIATELY);
            } elseif ($badge->isGoal()) {
                $bundle->attach(self::TRIGGER_GOAL_SET, self::IMMEDIATELY);
            }
        }
    }

    /**
     * Retracts a deleted Award.
     *
     * @param Award $award
     * @return Envelope
     */
    public function notifySystemDeleted(Award $award): Envelope
    {
        $envelope = new Envelope();
        return $this->retractAwarded($envelope, $award);
    }

    /**
     * Retracts a given Award.
     *
     * @param Envelope $envelope
     * @param Award    $award
     * @return Envelope
     */
    private function retractAwarded(Envelope $envelope, Award $award): Envelope
    {
        return $this->retractEventObject($envelope, $award);
    }


    /**
     * Notify relevant users in given role type that a goal has been set for a student
     *
     * @param string     $roleType
     * @param Award      $model
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    private function notifyGoalset(string $roleType, Award $model, User $effector, ?array $diff): Envelope
    {
        $envelope = new Envelope();

        try {
            /** @var Award $award */
            $award    = $this->mapper->findById($model->getId());
            $effector = $this->userMapper->findById($effector->getId());
        } catch (MapperException $e) {
            return $envelope;
        }

        // just in case
        if (!$award || !$award->isGoal()) {
            return $envelope;
        }

        $student   = $award->getRecipient();
        $awardHref = sprintf(self::PATTERN_AWARDED_GOAL_SET_HREF, $award->getId());

        $bob = new Builder();
        $bob->setIcon($this->getIcon($award))
            ->setHref($awardHref)
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addTopic("{$this->getSlug()}.{$award->getBadge()->getId()}." . self::TRIGGER_GOAL_SET)
            ->setTimestamp($award->getAwardedAt()->getUnixTimeStamp())
            ->addFilter(RoletypeFilter::NAME, [$roleType])
            ->putMeta('studentExternalId', $student->getExternalId());

        if ($effector->getRole()->isStaff()) {
            // a teacher set a goal for a student - to the student, the teachers of the student and parents of the student
            $bob->setTitle(self::PATTERN_STAFF_GOAL_SET_TITLE)
                ->setParams(
                    [
                        'teacher' => new UserParam($effector),
                        'student' => $roleType === Role::STUDENT ?
                            new Param('you', null, Param::STACKING_REPLACE_NO_DECORATION) : new UserParam($student),
                        'badge'   => new Param($award->getBadge()->getName(), $awardHref, Param::STACKING_APPEND),
                    ]
                );
        } elseif ($effector->getRole()->isStudent() && $student->getId() === $effector->getId()) {
            // a student posts - to the parent and teachers of the student
            $bob->setTitle(self::PATTERN_STUDENT_GOAL_SET_TITLE)
                ->setParams(
                    [
                        'student' => new UserParam($effector),
                        'badge'   => new Param($award->getBadge()->getName(), $awardHref, Param::STACKING_APPEND),
                    ]
                );
        } else {
            return $envelope;
        }
        $instance = $bob->build();
        $envelope->targetToParent($student->getId(), $instance);
        $envelope->targetToUser($student, $instance);

        //hydrate teachers for $student
        (new \Schoolbox\External\User())->loadTeachers([$student]);
        foreach ($student->getTeacherCollection() as $teacher) {
            $envelope->targetToUser($teacher, $instance);
        }
        return $envelope;
    }

    /**
     * Notify relevant users in given role type that a student has been awarded
     *
     * @param string     $roleType
     * @param Award      $model
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    private function notifyAwarded(string $roleType, Award $model, User $effector, ?array $diff): Envelope
    {
        $envelope = new Envelope();

        try {
            /** @var Model $award */
            $award    = $this->mapper->findById($model->getId());
            $effector = $this->userMapper->findById($effector->getId());
        } catch (MapperException $e) {
            return $envelope;
        }

        // just in case
        if (!$model->isAchievement()) {
            return $envelope;
        }

        $student   = $award->getRecipient();
        $awardHref = sprintf(self::PATTERN_AWARDED_GOAL_SET_HREF, $award->getId());

        $bob = new Builder();
        $bob->setIcon($this->getIcon($award))
            ->setHref($awardHref)
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addTopic("{$this->getSlug()}.{$award->getBadge()->getId()}." . self::TRIGGER_AWARDED)
            ->setTimestamp($award->getAwardedAt()->getUnixTimeStamp())
            ->addFilter(RoletypeFilter::NAME, [$roleType]);

        if ($effector->getRole()->isStaff() && Role::STUDENT === $roleType) {
            // a teacher awarded a student - to the student
            $bob->setTitle(self::PATTERN_STAFF_AWARDED_TITLE_STUDENT)
                ->setParams(
                    [
                        'teacher' => new UserParam($effector),
                        'badge'   => new Param($award->getBadge()->getName(), $awardHref, Param::STACKING_APPEND),
                        'message' => new SimpleParam(self::MESSAGES[rand(0, 2)]),
                    ]
                );
        } elseif ($effector->getRole()->isStaff() && Role::STUDENT !== $roleType) {
            // a teacher awarded a student - to the teachers of the student and parents of the student
            $bob->setTitle(self::PATTERN_STAFF_AWARDED_TITLE)
                ->setParams(
                    [
                        'teacher' => new UserParam($effector),
                        'student' => new UserParam($student),
                        'badge'   => new Param($award->getBadge()->getName(), $awardHref, Param::STACKING_APPEND),
                    ]
                )
                ->putMeta('studentExternalId', $student->getExternalId());
        } elseif ($effector->getRole()->isStudent() && $student->getId() === $effector->getId()) {
            // a student awarded self - to the parent and teachers of the student
            $bob->setTitle(self::PATTERN_STUDENT_AWARDED_TITLE)
                ->setParams(
                    [
                        'student' => new UserParam($effector),
                        'badge'   => new Param($award->getBadge()->getName(), $awardHref, Param::STACKING_APPEND),
                    ]
                )
                ->putMeta('studentExternalId', $effector->getExternalId());
        } else {
            return $envelope;
        }

        $instance = $bob->build();
        $envelope->targetToParent($student->getId(), $instance);
        $envelope->targetToUser($student, $instance);

        //hydrate teachers for $student
        (new \Schoolbox\External\User())->loadTeachers([$student]);
        foreach ($student->getTeacherCollection() as $teacher) {
            $envelope->targetToUser($teacher, $instance);
        }
        return $envelope;
    }

    /**
     * Notify students awarded.
     *
     * @param Award      $award
     * @param User       $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentAwarded(Award $award, User $user, ?array $diff): Envelope
    {
        return $this->notifyAwarded(Role::STUDENT, $award, $user, $diff);
    }

    /**
     * Notify staff awarded.
     *
     * @param Award      $award
     * @param User       $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffAwarded(Award $award, User $user, ?array $diff): Envelope
    {
        return $this->notifyAwarded(Role::STAFF, $award, $user, $diff);
    }

    /**
     * Notify parents awarded.
     *
     * @param Award      $award
     * @param User       $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentAwarded(Award $award, User $user, ?array $diff): Envelope
    {
        return $this->notifyAwarded(Role::PARENT, $award, $user, $diff);
    }

    /**
     * Notify students that a goal has been set.
     *
     * @param Award      $award
     * @param User       $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentGoalset(Award $award, User $user, ?array $diff): Envelope
    {
        return $this->notifyGoalSet(Role::STUDENT, $award, $user, $diff);
    }

    /**
     * Notify staff that that a goal has been set.
     *
     * @param Award      $award
     * @param User       $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffGoalset(Award $award, User $user, ?array $diff): Envelope
    {
        return $this->notifyGoalSet(Role::STAFF, $award, $user, $diff);
    }

    /**
     * Notify parents that a goal has been set.
     *
     * @param Award      $award
     * @param User       $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentGoalset(Award $award, User $user, ?array $diff): Envelope
    {
        return $this->notifyGoalSet(Role::PARENT, $award, $user, $diff);
    }

    /**
     * Gets image path suitable for award icon
     * @param Model $award
     * @return string
     */
    private function getIcon(Award $award): string
    {
        $badge = $award->getBadge();
        if ($badge->getIcon()) {
            $prefixGenerator = new PrefixGenerator($this->conf);
            return $prefixGenerator->generate() . $badge->getIcon();
        }
        if ($badge->getImage() && $badge->getImage()->getMimetype()->isImage()) {
            return "/storage/image.php?hash={$badge->getImage()->getHash()}&size=square192";
        }
        // should not happen, but just in case
        return $this->getUserIcon($award->getAwardedBy());
    }
}
