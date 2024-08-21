<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Discussion\Comment;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Schoolbox\Discussion\Comment\Model as Comment;
use Schoolbox\Discussion\Thread\Mapper as ThreadMapper;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Moment\Evidence\Discussion\ThreadLocator;
use Schoolbox\Learning\Moment\Evidence\Mapper as EvidenceMapper;
use Schoolbox\Learning\Moment\Evidence\Model as Evidence;
use Schoolbox\Mapper;
use Schoolbox\MapperFactory;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\ChildParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Discussion\Comment\Mapper as CommentMapper;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Notifier extends AbstractModuleNotifier
{
    public const TRIGGER_COMMENTED = 'commented';

    private const PATTERN_COMMENTED_OWN_TITLE = ':user commented on :student :evidence';
    private const PATTERN_COMMENTED_TITLE     = ":user commented on :student's :evidence";
    private const PATTERN_EVIDENCE_HREF       = '/learning/evidence/%d';

    private ThreadMapper $threadMapper;

    private EvidenceMapper $evidenceMapper;

    private UserMapper $userMapper;

    /**
     * @inheritdoc
     */
    public static function getSetters(ContainerInterface $di): array
    {
        /** @var MapperFactory $mf */
        $mf = $di->get('mapperFactory');


        return [
            'setMapper'         => $mf->get(CommentMapper::class),
            'setThreadMapper'   => $mf->get(ThreadMapper::class),
            'setEvidenceMapper' => $mf->get(EvidenceMapper::class),
            'setUserMapper'     => $mf->get(UserMapper::class),
        ];
    }


    /**
     * @inheritdoc
     */
    public function __construct(IConfigLoader $configLoader, array $dependencies = [])
    {
        parent::__construct($configLoader, ['mapper' => CommentMapper::getInstance()] + $dependencies);
    }

    /**
     * @inheritdoc
     */
    protected function getSlug(): string
    {
        return NSMapper::NS_EVIDENCE_COMMENT;
    }

    /**
     * Setter for ThreadMapper
     * @param ThreadMapper $threadMapper
     */
    public function setThreadMapper(ThreadMapper $threadMapper)
    {
        $this->threadMapper = $threadMapper;
    }

    /**
     * Setter for EvidenceMapper
     * @param EvidenceMapper $evidenceMapper
     */
    public function setEvidenceMapper(EvidenceMapper $evidenceMapper)
    {
        $this->evidenceMapper = $evidenceMapper;
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
     * Attaches and detaches triggers properly depending on the article status and transition
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
        if ($crudMethod === Mapper::METHOD_DELETE) {
            // comment is deleted
            $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
        }

        /**
         * Created
         */
        if ($crudMethod === Mapper::METHOD_CREATE) {
            $bundle->attach(self::TRIGGER_COMMENTED, self::IMMEDIATELY);
        }
    }

    /**
     * Retracts the deleted comment
     * @param Comment $comment
     * @return Envelope
     */
    public function notifySystemDeleted(Comment $comment): Envelope
    {
        $envelope = new Envelope();
        return $this->retractEventObject($envelope, $comment);
    }


    /**
     * Notifies staff that someone has commented on his learning moment
     * @param Comment    $comment
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffCommented(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyCommented(Role::STAFF, $comment, $effector);
    }


    /**
     * Notifies student that someone has commented on his learning moment
     * @param Comment    $comment
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentCommented(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyCommented(Role::STUDENT, $comment, $effector);
    }


    /**
     * Notifies parent that someone has commented on his learning moment
     * @param Comment    $comment
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentCommented(Comment $comment, User $effector, ?array $diff = []): Envelope
    {
        return $this->notifyCommented(Role::PARENT, $comment, $effector);
    }

    /**
     * @param string  $roleType
     * @param Comment $comment
     * @param User    $effector
     * @return Envelope
     */
    private function notifyCommented(string $roleType, Comment $comment, User $effector): Envelope
    {
        // get the item that the comment has created on
        $thread        = $comment->getThread();
        $threadLocator = new ThreadLocator($this->threadMapper, $this->evidenceMapper);

        /** @var GenericCollection $evidences */
        $evidences = $threadLocator->findAssociations($thread);
        $envelope  = new Envelope();

        if ($evidences->count() === 0) {
            return $envelope;
        }

        /** @var Evidence $evidence */
        $evidence     = $evidences->first();
        $evidenceHref = sprintf(self::PATTERN_EVIDENCE_HREF, $evidence->getId());
        $student      = $evidence->getRecipient();

        $bob = new Builder();

        $bob->setNamespace($this->getSlug())
            ->setIcon($this->getUserIcon($effector))
            ->setSeverity(Logger::INFO)
            ->setFilter(['roletype' => $roleType])
            ->setHref(sprintf(self::PATTERN_EVIDENCE_HREF, $evidence->getId()))
            ->addTopic("{$this->getSlug()}.{$comment->getId()}." . self::TRIGGER_COMMENTED)
            ->setBody($comment->getContent());

        $isOwn = $effector->getId() === $student->getId();
        if ($roleType === Role::STUDENT) {
            $bob->setTitle(self::PATTERN_COMMENTED_OWN_TITLE)
                ->setParams(
                    [
                        'user'     => new UserParam($effector),
                        'student'  => new Param('your', null, Param::STACKING_REPLACE_NO_DECORATION),
                        'evidence' => new Param('learning moment', $evidenceHref, Param::STACKING_REPLACE_NO_DECORATION),
                    ]
                );
            $instance = $bob->build();
            $envelope->targetToUser($student, $instance);
        } elseif ($roleType === Role::PARENT) {
            $bob->setTitle($isOwn ? self::PATTERN_COMMENTED_OWN_TITLE : self::PATTERN_COMMENTED_TITLE)
                ->setParams(
                    [
                        'user'     => $isOwn
                            ? new ChildParam($student)
                            : new UserParam($effector),
                        'student'  => $isOwn
                            ? new Param('their', null, Param::STACKING_REPLACE_NO_DECORATION)
                            : new ChildParam($student),
                        'evidence' => new Param('learning moment', $evidenceHref, Param::STACKING_REPLACE_NO_DECORATION),
                    ]
                );
            $instance = $bob->build();
            $envelope->targetToParent($student->getId(), $instance);
        } elseif ($roleType === Role::STAFF) {
            try {
                // get hydrated creator
                $targetTo = $this->userMapper->findById($evidence->getCreator()->getId());
            } catch (Mapper\Exception $e) {
                return new Envelope();
            }
            if (!$targetTo->getRole()->getAccessEvidence()) {
                return new Envelope();
            }
            $bob->setTitle($isOwn ? self::PATTERN_COMMENTED_OWN_TITLE : self::PATTERN_COMMENTED_TITLE)
                ->setParams(
                    [
                        'user'     => new UserParam($effector),
                        'student'  => $isOwn
                            ? new Param('their', null, Param::STACKING_REPLACE_NO_DECORATION)
                            : new UserParam($student),
                        'evidence' => new Param('learning moment', $evidenceHref, Param::STACKING_REPLACE_NO_DECORATION),
                    ]
                );
            $instance = $bob->build();
            $envelope->targetToUser($targetTo, $instance);
        } else {
            return new Envelope();
        }
        return $envelope;
    }
}
