<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Participant\Plan\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as DueWorkFeedbackMapper;
use Schoolbox\Assessment\Participant\Plan\Mapper;
use Schoolbox\Assessment\Participant\Plan\Model;
use Schoolbox\Assessment\Participant\Plan\Notifier;
use Schoolbox\Collection;
use Schoolbox\External;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Model as DomainDueWork;
use Schoolbox\Learning\Assessment\Participant as DomainParticipant;
use Schoolbox\Learning\Assessment\Plan as DomainPlan;
use Schoolbox\Learning\Assessment\Project\ResultCache\Mapper as ProjectResultCacheMapper;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizFeedbackMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Message\Source;
use Schoolbox\Realtime\Message\SourceMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Event\Event;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Tests participant plan notifier behaviour for work type visibility and feed audiences.
 */
final class NotifierVisibilityTest extends TestCase implements TriggerBundleInterface
{
    /** @var array<string,int|callable> */
    public array $attached = [];

    /** @var string[] */
    public array $detached = [];

    private ?Event $event = null;

    private string $method = '';

    private const WORK_TYPE_SI = 1;
    private const WORK_TYPE_SIL = 2;
    private const WORK_TYPE_SIP = 3;
    private const WORK_TYPE_SILP = 4;

    private const AUDIENCE_LEARNER = 'learner';
    private const AUDIENCE_PARENT = 'parent';
    private const AUDIENCE_STAFF = 'staff';

    /** @var string[] */
    private const SHARED_VISIBLE_TRIGGERS = [
        'open',
        'upcoming',
        'overdue',
        'closed',
    ];

    /** @var string[] */
    private const HIDDEN_DETACHED_TRIGGERS = [
        'open',
        'upcoming',
        'updated',
        'overdue',
        'closed',
    ];

    /**
     * Records a trigger attachment requested by the notifier under test.
     *
     * @param string       $label
     * @param int|callable $timestamp
     */
    public function attach($label, $timestamp)
    {
        $this->attached[$label] = $timestamp;
    }

    /**
     * Records a batch of trigger attachments requested by the notifier under test.
     *
     * @param array<string,int|callable> $array
     */
    public function attachEach($array)
    {
        foreach ($array as $label => $timestamp) {
            $this->attach($label, $timestamp);
        }
    }

    /**
     * Records a trigger detachment requested by the notifier under test.
     *
     * @param string $label
     */
    public function detach($label)
    {
        $this->detached[] = $label;
    }

    /**
     * Records a batch of trigger detachments requested by the notifier under test.
     *
     * @param string[] $array
     */
    public function detachEach($array)
    {
        foreach ($array as $label) {
            $this->detach($label);
        }
    }

    /**
     * Returns the mapper method represented by the fake trigger bundle event.
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Returns the event prepared for the fake trigger bundle.
     *
     * @return Event
     */
    public function getEvent()
    {
        self::assertNotNull($this->event);

        return $this->event;
    }

    /**
     * Returns no existing triggers for this fake trigger bundle.
     *
     * @return array<int,mixed>
     */
    public function getTriggers()
    {
        return [];
    }

    /**
     * Tests trigger bundling for newly created assessments by work type visibility.
     *
     * @param int      $currentWorkTypeId     Work type fixture ID for the created assessment.
     * @param string[] $expectedAttachedLabels Expected trigger labels to attach.
     * @param string[] $expectedDetachedLabels Expected trigger labels to detach.
     */
    #[DataProvider('provideCreateWorkTypeTransitions')]
    public function testCreateBundlesTriggersForVisibleWorkTypesOnly(
        int $currentWorkTypeId,
        array $expectedAttachedLabels,
        array $expectedDetachedLabels
    ): void {
        $workTypes = $this->workTypes();
        $notifier = $this->notifierWithWorkTypes($workTypes);
        $bundle = $this->bundleForWorkTypeChange(
            BaseMapper::METHOD_CREATE,
            $workTypes[$currentWorkTypeId],
            null
        );

        $notifier->bundleTriggers($bundle);

        self::assertSame($expectedAttachedLabels, array_keys($bundle->attached));
        self::assertSame($expectedDetachedLabels, $bundle->detached);
    }

    /**
     * Tests trigger bundling when an assessment changes between work type visibility states.
     *
     * @param int      $previousWorkTypeId    Work type fixture ID before the update.
     * @param int      $currentWorkTypeId     Work type fixture ID after the update.
     * @param string[] $expectedAttachedLabels Expected trigger labels to attach.
     * @param string[] $expectedDetachedLabels Expected trigger labels to detach.
     */
    #[DataProvider('provideUpdateWorkTypeTransitions')]
    public function testUpdateBundlesTriggersForWorkTypeVisibilityTransitions(
        int $previousWorkTypeId,
        int $currentWorkTypeId,
        array $expectedAttachedLabels,
        array $expectedDetachedLabels
    ): void {
        $workTypes = $this->workTypes();
        $notifier = $this->notifierWithWorkTypes($workTypes);
        $bundle = $this->bundleForWorkTypeChange(
            BaseMapper::METHOD_UPDATE,
            $workTypes[$currentWorkTypeId],
            $previousWorkTypeId
        );

        $notifier->bundleTriggers($bundle);

        self::assertSame($expectedAttachedLabels, array_keys($bundle->attached));
        self::assertSame($expectedDetachedLabels, $bundle->detached);
    }

    /**
     * Tests learner and parent upcoming notifications against work type visibility.
     *
     * @param int      $workTypeId           Work type fixture ID for the assessment.
     * @param string   $method               Role-specific notifier method to call.
     * @param string[] $expectedDestinations Expected notification destination keys.
     */
    #[DataProvider('provideRoleSpecificUpcomingNotifications')]
    public function testRoleSpecificUpcomingNotificationsRespectWorkTypeVisibility(
        int $workTypeId,
        string $method,
        array $expectedDestinations
    ): void {
        $workTypes = $this->workTypes();
        $assessment = $this->domainDueWork($workTypes[$workTypeId]);
        $notifier = $this->notifierWithDomainAssessment($assessment);
        $plan = $this->dataPlanForAssessment($assessment->getId());

        $envelope = $notifier->{$method}($plan);

        self::assertSame($expectedDestinations, array_keys($envelope->getAll()));
    }

    /**
     * Tests that callback discovery uses role-specific message source configuration.
     *
     * @param string   $label           Notification trigger label to discover callbacks for.
     * @param Source[] $sources         Message sources returned by the source mapper.
     * @param string[] $expectedMethods Expected callback method names.
     */
    #[DataProvider('provideCallbackDiscovery')]
    public function testRetrieveCallbacksUsesRoleSpecificMessageSources(
        string $label,
        array $sources,
        array $expectedMethods
    ): void {
        $notifier = $this->notifierWithMessageSources($label, $sources);

        $callbacks = $notifier->retrieveCallbacks($label);
        $methods = array_map(static fn (array $callback): string => $callback[1], $callbacks);

        self::assertSame($expectedMethods, $methods);
    }

    /**
     * Tests participant-included update notifications against the selected message audience.
     *
     * @param string   $method               Role-specific notifier method to call.
     * @param string[] $expectedDestinations Expected notification destination keys.
     */
    #[DataProvider('provideRoleSpecificParticipantIncludedNotifications')]
    public function testRoleSpecificParticipantIncludedNotificationsRespectRequestedAudience(
        string $method,
        array $expectedDestinations
    ): void {
        $workTypes = $this->workTypes();
        $assessment = $this->domainDueWork($workTypes[self::WORK_TYPE_SILP]);
        $notifier = $this->notifierWithDomainAssessment($assessment);
        $plan = $this->dataPlanForAssessment($assessment->getId());
        $diff = [
            'participants' => [
                501 => [
                    'excluded' => [false, true],
                ],
            ],
        ];

        $envelope = $notifier->{$method}($plan, new User(), $diff);

        self::assertSame($expectedDestinations, array_keys($envelope->getAll()));
    }

    /**
     * Tests parent-specific update notifications that previously inherited learner topics.
     *
     * @param array<string,mixed> $diff                 Update diff passed to the notifier.
     * @param string[]            $expectedDestinations Expected notification destination keys.
     */
    #[DataProvider('provideParentUpdatedNotifications')]
    public function testParentUpdatedNotificationsBuildParentMessagesWithoutLearnerBranch(
        array $diff,
        array $expectedDestinations
    ): void {
        $workTypes = $this->workTypes();
        $assessment = $this->domainDueWork($workTypes[self::WORK_TYPE_SIP]);
        $notifier = $this->notifierWithDomainAssessment($assessment);
        $plan = $this->dataPlanForAssessment($assessment->getId());

        $envelope = $notifier->notifyParentUpdated($plan, new User(), $diff);

        self::assertSame($expectedDestinations, array_keys($envelope->getAll()));
    }

    /**
     * Tests that feed notification builders only build messages for the requested audience.
     *
     * @param int      $workTypeId           Work type fixture ID for the assessment.
     * @param string   $methodName           Internal feed notification method to call.
     * @param string   $feedAudience         Feed audience to request.
     * @param string[] $expectedDestinations Expected notification destination keys.
     */
    #[DataProvider('provideFeedAudienceNotifications')]
    public function testFeedAudienceNotificationsBuildOnlyRequestedAudienceMessages(
        int $workTypeId,
        string $methodName,
        string $feedAudience,
        array $expectedDestinations
    ): void {
        $workTypes = $this->workTypes();
        $assessment = $this->domainDueWork($workTypes[$workTypeId]);
        $notifier = $this->notifierWithDomainAssessment($assessment);
        $plan = $this->dataPlanForAssessment($assessment->getId());

        $method = new \ReflectionMethod(Notifier::class, $methodName);
        $envelope = $method->invoke($notifier, $assessment, $plan, $feedAudience);

        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertSame($expectedDestinations, array_keys($envelope->getAll()));
    }

    /**
     * Tests that feed audience selection checks folder access with a folder ID.
     *
     * @param string $roleType         Role type assigned to the feed viewer.
     * @param int    $folderAccess     Folder access level returned by the access service.
     * @param string $expectedAudience Expected feed audience classification.
     */
    #[DataProvider('provideFeedAudienceSelection')]
    public function testFeedAudienceSelectionChecksAccessByFolderId(
        string $roleType,
        int $folderAccess,
        string $expectedAudience
    ): void {
        $workTypes = $this->workTypes();
        $assessment = $this->domainDueWork($workTypes[self::WORK_TYPE_SILP]);
        $notifier = $this->notifierWithDomainAssessment($assessment);
        $user = (new User())
            ->setId(701)
            ->setRole((new Role())->setId(801)->setType($roleType));
        $access = $this->createStub(Access::class);
        $access
            ->method('getFolderAccess')
            ->willReturnCallback(
                /**
                 * Asserts feed audience selection passes the folder ID, not the folder model.
                 *
                 * @param User $actualUser User passed to the access service.
                 * @param int  $folderId   Folder ID passed to the access service.
                 */
                static function (User $actualUser, int $folderId) use ($user, $folderAccess): int {
                    self::assertSame($user, $actualUser);
                    self::assertSame(401, $folderId);

                    return $folderAccess;
                }
            );

        $method = new \ReflectionMethod(Notifier::class, 'getFeedAudience');

        self::assertSame($expectedAudience, $method->invoke($notifier, $user, $assessment, $access));
    }

    /**
     * Provides created assessment work type visibility cases for trigger bundling.
     *
     * @return array<string,array{int,string[],string[]}>
     */
    public static function provideCreateWorkTypeTransitions(): array
    {
        return [
            'CREATE SI' => [self::WORK_TYPE_SI, [], []],
            'CREATE SIL' => [self::WORK_TYPE_SIL, self::SHARED_VISIBLE_TRIGGERS, []],
            'CREATE SIP' => [self::WORK_TYPE_SIP, self::SHARED_VISIBLE_TRIGGERS, []],
            'CREATE SILP' => [self::WORK_TYPE_SILP, self::SHARED_VISIBLE_TRIGGERS, []],
        ];
    }

    /**
     * Provides work type visibility transition cases for trigger bundling.
     *
     * @return array<string,array{int,int,string[],string[]}>
     */
    public static function provideUpdateWorkTypeTransitions(): array
    {
        return [
            'UPDATE SI to SIL' => [
                self::WORK_TYPE_SI,
                self::WORK_TYPE_SIL,
                self::SHARED_VISIBLE_TRIGGERS,
                [],
            ],
            'UPDATE SI to SIP' => [
                self::WORK_TYPE_SI,
                self::WORK_TYPE_SIP,
                self::SHARED_VISIBLE_TRIGGERS,
                [],
            ],
            'UPDATE SI to SILP' => [
                self::WORK_TYPE_SI,
                self::WORK_TYPE_SILP,
                self::SHARED_VISIBLE_TRIGGERS,
                [],
            ],
            'UPDATE SIL to SI' => [
                self::WORK_TYPE_SIL,
                self::WORK_TYPE_SI,
                ['deleted'],
                self::HIDDEN_DETACHED_TRIGGERS,
            ],
            'UPDATE SIP to SI' => [
                self::WORK_TYPE_SIP,
                self::WORK_TYPE_SI,
                ['deleted'],
                self::HIDDEN_DETACHED_TRIGGERS,
            ],
            'UPDATE SILP to SI' => [
                self::WORK_TYPE_SILP,
                self::WORK_TYPE_SI,
                ['deleted'],
                self::HIDDEN_DETACHED_TRIGGERS,
            ],
            'UPDATE SIL to SIP' => [self::WORK_TYPE_SIL, self::WORK_TYPE_SIP, [], []],
            'UPDATE SIP to SIL' => [self::WORK_TYPE_SIP, self::WORK_TYPE_SIL, [], []],
            'UPDATE SIL to SILP' => [self::WORK_TYPE_SIL, self::WORK_TYPE_SILP, [], []],
            'UPDATE SIP to SILP' => [self::WORK_TYPE_SIP, self::WORK_TYPE_SILP, [], []],
            'UPDATE SILP to SIL' => [self::WORK_TYPE_SILP, self::WORK_TYPE_SIL, [], []],
            'UPDATE SILP to SIP' => [self::WORK_TYPE_SILP, self::WORK_TYPE_SIP, [], []],
        ];
    }

    /**
     * Provides role-specific upcoming notification cases by work type visibility.
     *
     * @return array<string,array{int,string,string[]}>
     */
    public static function provideRoleSpecificUpcomingNotifications(): array
    {
        return [
            'student source ignores staff-only work type' => [
                self::WORK_TYPE_SI,
                'notifyStudentUpcoming',
                [],
            ],
            'parent source ignores staff-only work type' => [
                self::WORK_TYPE_SI,
                'notifyParentUpcoming',
                [],
            ],
            'student source sends learner-visible work type' => [
                self::WORK_TYPE_SIL,
                'notifyStudentUpcoming',
                ['notify.user.501.duework.101.upcoming'],
            ],
            'parent source ignores learner-only work type' => [
                self::WORK_TYPE_SIL,
                'notifyParentUpcoming',
                [],
            ],
            'student source ignores parent-only work type' => [
                self::WORK_TYPE_SIP,
                'notifyStudentUpcoming',
                [],
            ],
            'parent source sends parent-visible work type' => [
                self::WORK_TYPE_SIP,
                'notifyParentUpcoming',
                ['notify.parent.501.duework.101.upcoming'],
            ],
            'student source sends learner-parent-visible work type' => [
                self::WORK_TYPE_SILP,
                'notifyStudentUpcoming',
                ['notify.user.501.duework.101.upcoming'],
            ],
            'parent source sends learner-parent-visible work type' => [
                self::WORK_TYPE_SILP,
                'notifyParentUpcoming',
                ['notify.parent.501.duework.101.upcoming'],
            ],
        ];
    }

    /**
     * Provides participant-included update notification cases by message audience.
     *
     * @return array<string,array{string,string[]}>
     */
    public static function provideRoleSpecificParticipantIncludedNotifications(): array
    {
        return [
            'student update source builds learner open destination' => [
                'notifyStudentUpdated',
                ['notify.user.501.duework.101.open'],
            ],
            'parent update source does not build learner open destination' => [
                'notifyParentUpdated',
                [],
            ],
        ];
    }

    /**
     * Provides parent update notification cases whose topics are set without learner messages.
     *
     * @return array<string,array{array<string,mixed>,string[]}>
     */
    public static function provideParentUpdatedNotifications(): array
    {
        return [
            'renamed due work' => [
                [
                    'assessment' => [
                        'title' => ['Visibility due work renamed', 'Visibility due work'],
                    ],
                ],
                ['notify.parent.501.duework.101.renamed'],
            ],
            'due date changed' => [
                [
                    'participants' => [
                        501 => [
                            'dueDate' => ['2026-05-09 17:00:00', '2026-05-09 16:00:00'],
                        ],
                    ],
                ],
                ['notify.parent.501.duework.101.datechanged'],
            ],
            'accept online changed' => [
                [
                    'assessment' => [
                        'acceptOnline' => [1, 0],
                    ],
                ],
                ['notify.parent.501.duework.101.updated'],
            ],
            'work type changed' => [
                [
                    'assessment' => [
                        'workType' => [self::WORK_TYPE_SIP, self::WORK_TYPE_SI],
                    ],
                ],
                ['notify.parent.501.duework.101.updated'],
            ],
        ];
    }

    /**
     * Provides feed notification cases by work type visibility and feed audience.
     *
     * @return array<string,array{int,string,string,string[]}>
     */
    public static function provideFeedAudienceNotifications(): array
    {
        return [
            'open learner feed builds learner-visible destination' => [
                self::WORK_TYPE_SIL,
                'internalNotifyOpen',
                self::AUDIENCE_LEARNER,
                ['notify.user.501.duework.101.open'],
            ],
            'open learner feed ignores parent-only work type' => [
                self::WORK_TYPE_SIP,
                'internalNotifyOpen',
                self::AUDIENCE_LEARNER,
                [],
            ],
            'open parent feed builds parent-visible destination' => [
                self::WORK_TYPE_SIP,
                'internalNotifyOpen',
                self::AUDIENCE_PARENT,
                ['notify.parent.501.duework.101.open'],
            ],
            'open parent feed ignores learner-only work type' => [
                self::WORK_TYPE_SIL,
                'internalNotifyOpen',
                self::AUDIENCE_PARENT,
                [],
            ],
            'open staff feed builds learner-visible destination' => [
                self::WORK_TYPE_SIL,
                'internalNotifyOpen',
                self::AUDIENCE_STAFF,
                ['notify.service.1.401.2.duework.101.open'],
            ],
            'open staff feed builds parent-visible destination' => [
                self::WORK_TYPE_SIP,
                'internalNotifyOpen',
                self::AUDIENCE_STAFF,
                ['notify.service.1.401.2.duework.101.open'],
            ],
            'open staff feed builds learner-parent-visible destination' => [
                self::WORK_TYPE_SILP,
                'internalNotifyOpen',
                self::AUDIENCE_STAFF,
                ['notify.service.1.401.2.duework.101.open'],
            ],
            'closed learner feed builds learner-visible destination' => [
                self::WORK_TYPE_SIL,
                'internalNotifyClosed',
                self::AUDIENCE_LEARNER,
                ['notify.user.501.duework.101.closed'],
            ],
            'closed learner feed ignores parent-only work type' => [
                self::WORK_TYPE_SIP,
                'internalNotifyClosed',
                self::AUDIENCE_LEARNER,
                [],
            ],
            'closed parent feed builds parent-visible destination' => [
                self::WORK_TYPE_SIP,
                'internalNotifyClosed',
                self::AUDIENCE_PARENT,
                ['notify.parent.501.duework.101.closed'],
            ],
            'closed parent feed ignores learner-only work type' => [
                self::WORK_TYPE_SIL,
                'internalNotifyClosed',
                self::AUDIENCE_PARENT,
                [],
            ],
            'closed staff feed builds learner-visible destination' => [
                self::WORK_TYPE_SIL,
                'internalNotifyClosed',
                self::AUDIENCE_STAFF,
                ['notify.service.1.401.2.duework.101.closed'],
            ],
            'closed staff feed builds parent-visible destination' => [
                self::WORK_TYPE_SIP,
                'internalNotifyClosed',
                self::AUDIENCE_STAFF,
                ['notify.service.1.401.2.duework.101.closed'],
            ],
            'closed staff feed builds learner-parent-visible destination' => [
                self::WORK_TYPE_SILP,
                'internalNotifyClosed',
                self::AUDIENCE_STAFF,
                ['notify.service.1.401.2.duework.101.closed'],
            ],
        ];
    }

    /**
     * Provides role and access combinations for feed audience selection.
     *
     * @return array<string,array{string,int,string}>
     */
    public static function provideFeedAudienceSelection(): array
    {
        return [
            'read access viewer is learner audience' => [
                Role::STUDENT,
                UserAccess::ACCESS_READ,
                self::AUDIENCE_LEARNER,
            ],
            'write access viewer is staff audience' => [
                Role::STAFF,
                UserAccess::ACCESS_WRITE,
                self::AUDIENCE_STAFF,
            ],
        ];
    }

    /**
     * Provides message source combinations for callback discovery.
     *
     * @return array<string,array{string,Source[],string[]}>
     */
    public static function provideCallbackDiscovery(): array
    {
        return [
            'upcoming dispatch uses student and parent callbacks' => [
                'upcoming',
                [
                    self::source('upcoming', Role::STUDENT),
                    self::source('upcoming', Role::PARENT),
                ],
                ['notifyStudentUpcoming', 'notifyParentUpcoming'],
            ],
            'overdue dispatch uses student and parent callbacks' => [
                'overdue',
                [
                    self::source('overdue', Role::STUDENT),
                    self::source('overdue', Role::PARENT),
                ],
                ['notifyStudentOverdue', 'notifyParentOverdue'],
            ],
            'updated dispatch uses student and parent callbacks' => [
                'updated',
                [
                    self::source('updated', Role::STUDENT),
                    self::source('updated', Role::PARENT),
                ],
                ['notifyStudentUpdated', 'notifyParentUpdated'],
            ],
            'open dispatch uses student callback' => [
                'open',
                [self::source('open', Role::STUDENT)],
                ['notifyStudentOpen'],
            ],
            'closed dispatch uses staff callback' => [
                'closed',
                [self::source('closed', Role::STAFF)],
                ['notifyStaffClosed'],
            ],
        ];
    }

    /**
     * Builds a message source model with the supplied key and role type.
     *
     * @param string $key      Message source key.
     * @param string $roleType Message source role type.
     */
    private static function source(string $key, string $roleType): Source
    {
        $source = new Source();
        $source->setKey($key);
        $source->setRoleType($roleType);

        return $source;
    }

    /**
     * Builds the work type fixtures used by the visibility matrix tests.
     *
     * @return array<int,WorkType\Model>
     */
    private function workTypes(): array
    {
        return [
            self::WORK_TYPE_SI => $this->workType(self::WORK_TYPE_SI, false, false),
            self::WORK_TYPE_SIL => $this->workType(self::WORK_TYPE_SIL, true, false),
            self::WORK_TYPE_SIP => $this->workType(self::WORK_TYPE_SIP, false, true),
            self::WORK_TYPE_SILP => $this->workType(self::WORK_TYPE_SILP, true, true),
        ];
    }

    /**
     * Builds a work type fixture with explicit learner and parent visibility.
     *
     * @param int  $id       Work type ID.
     * @param bool $learners Whether learners can see the work type.
     * @param bool $parents  Whether parents can see the work type.
     */
    private function workType(int $id, bool $learners, bool $parents): WorkType\Model
    {
        return (new WorkType\Model())
            ->setId($id)
            ->setName('Homework')
            ->setIsVisibleToLearners($learners)
            ->setIsVisibleToParents($parents);
    }

    /**
     * Builds a notifier with a work type mapper backed by the supplied fixtures.
     *
     * @param array<int,WorkType\Model> $workTypes Work type fixtures keyed by ID.
     */
    private function notifierWithWorkTypes(array $workTypes): Notifier
    {
        $workTypeMapper = $this->createStub(WorkType\Mapper::class);
        $workTypeMapper
            ->method('findById')
            ->willReturnCallback(static fn (int $id): WorkType\Model => $workTypes[$id]);

        return new Notifier(
            $this->createStub(IConfigLoader::class),
            [
                'workTypeMapper' => $workTypeMapper,
                'assessmentMapper' => $this->createStub(AssessmentMapper::class),
                'assessmentDataSource' => $this->createStub(AssessmentDataSource::class),
                'dueWorkFeedbackMapper' => $this->createStub(DueWorkFeedbackMapper::class),
                'quizFeedbackMapper' => $this->createStub(QuizFeedbackMapper::class),
                'externalUser' => $this->createStub(External\User::class),
                'nsMapper' => $this->createStub(NSMapper::class),
                'srcMapper' => $this->createStub(SourceMapper::class),
                'mapper' => $this->createStub(Mapper::class),
            ]
        );
    }

    /**
     * Builds a notifier whose source mapper returns the supplied message sources.
     *
     * @param string   $label   Notification trigger label for the source query.
     * @param Source[] $sources Message sources returned by the source mapper.
     */
    private function notifierWithMessageSources(string $label, array $sources): Notifier
    {
        $namespace = new NS();
        $namespace->setId(901);
        $namespace->setSlug(NSMapper::NS_DUEWORK);

        $nsMapper = $this->createStub(NSMapper::class);
        $nsMapper
            ->method('findOneBySlug')
            ->willReturn($namespace);

        $srcMapper = $this->createMock(SourceMapper::class);
        $srcMapper
            ->expects($this->once())
            ->method('findByColumnList')
            ->with([
                'namespace' => $namespace->getId(),
                'key' => $label,
            ])
            ->willReturn(new Collection($sources));

        return new Notifier(
            $this->createStub(IConfigLoader::class),
            [
                'workTypeMapper' => $this->createStub(WorkType\Mapper::class),
                'assessmentMapper' => $this->createStub(AssessmentMapper::class),
                'assessmentDataSource' => $this->createStub(AssessmentDataSource::class),
                'dueWorkFeedbackMapper' => $this->createStub(DueWorkFeedbackMapper::class),
                'quizFeedbackMapper' => $this->createStub(QuizFeedbackMapper::class),
                'externalUser' => $this->createStub(External\User::class),
                'nsMapper' => $nsMapper,
                'srcMapper' => $srcMapper,
                'mapper' => $this->createStub(Mapper::class),
            ]
        );
    }

    /**
     * Builds a notifier that retrieves the supplied domain assessment.
     *
     * @param DomainDueWork $assessment Domain assessment returned by the assessment data source.
     */
    private function notifierWithDomainAssessment(DomainDueWork $assessment): Notifier
    {
        $assessmentDataSource = $this->createStub(AssessmentDataSource::class);
        $assessmentDataSource
            ->method('findByIdForReview')
            ->willReturn($assessment);

        $assessmentMapper = $this->createStub(AssessmentMapper::class);
        $assessmentMapper
            ->method('findById')
            ->willReturn($this->dataAssessment($assessment->getId()));

        $config = $this->createStub(IConfigLoader::class);
        $config
            ->method('get')
            ->willReturn(false);

        return new Notifier(
            $config,
            [
                'workTypeMapper' => $this->createStub(WorkType\Mapper::class),
                'assessmentMapper' => $assessmentMapper,
                'assessmentDataSource' => $assessmentDataSource,
                'dueWorkFeedbackMapper' => $this->createStub(DueWorkFeedbackMapper::class),
                'quizFeedbackMapper' => $this->createStub(QuizFeedbackMapper::class),
                'externalUser' => $this->createStub(External\User::class),
                'nsMapper' => $this->createStub(NSMapper::class),
                'srcMapper' => $this->createStub(SourceMapper::class),
                'mapper' => $this->createStub(Mapper::class),
            ]
        );
    }

    /**
     * Builds a domain due work assessment with one dynamic plan and one participant.
     *
     * @param WorkType\Model $workType Work type assigned to the assessment.
     */
    private function domainDueWork(WorkType\Model $workType): DomainDueWork
    {
        $now = Alaress_Date_Format::newFromTimestamp(time(), null, new \Alaress_Date_Locale_Static());
        $folder = (new Folder())
            ->setId(401)
            ->setName('Test Folder');

        $assessment = (new DomainDueWork())
            ->setId(201)
            ->setTitle('Visibility due work')
            ->setDescription('')
            ->setContext($folder)
            ->setWorkType($workType)
            ->setPublishAt((clone $now)->addHours(-2))
            ->setOpenAt((clone $now)->addHours(-1))
            ->setDueAt((clone $now)->addHours(3))
            ->setCloseAt((clone $now)->addHours(4))
            ->setAcceptsSingle();

        $plan = (new DomainPlan($assessment))
            ->setId(301)
            ->setIsDynamic(Mapper::PLAN_TYPE_DYNAMIC)
            ->setName('Dynamic')
            ->setPublishDate(null)
            ->setOpenDate(null)
            ->setDueDate(null)
            ->setCloseDate(null);

        $student = (new User())
            ->setId(501)
            ->setFirstname('Jenni')
            ->setPreferredName('Jenni')
            ->setLastname('Reed');

        $participant = (new DomainParticipant(
            $student,
            $plan,
            $this->createStub(ProjectResultCacheMapper::class)
        ))
            ->setId(601)
            ->setExcludedAt(null);

        $plan->getParticipants()->setOffset($student->getId(), $participant);
        $assessment->setPlans(new GenericCollection(DomainPlan::class, [$plan]));

        return $assessment;
    }

    /**
     * Builds a data-model participant plan for the supplied assessment ID.
     *
     * @param int $assessmentId Assessment ID to set on the plan's data assessment.
     */
    private function dataPlanForAssessment(int $assessmentId): Model
    {
        return (new Model())
            ->setId(301)
            ->setAssessment($this->dataAssessment($assessmentId))
            ->setIsDynamic(Mapper::PLAN_TYPE_DYNAMIC);
    }

    /**
     * Builds a data-model assessment containing the due work component used by notifier routes.
     *
     * @param int $assessmentId Assessment ID to set on the data model.
     */
    private function dataAssessment(int $assessmentId): DataModel\Model
    {
        $dueWork = (new SubmissionBox())
            ->setId(101);

        return (new DataModel\Model())
            ->setId($assessmentId)
            ->setDueWork($dueWork);
    }

    /**
     * Builds this test case as a trigger bundle for a work type visibility change.
     *
     * @param string         $method             Mapper method represented by the trigger bundle event.
     * @param WorkType\Model $currentWorkType    Current work type assigned to the assessment.
     * @param int|null       $previousWorkTypeId Previous work type ID from the update diff.
     */
    private function bundleForWorkTypeChange(
        string $method,
        WorkType\Model $currentWorkType,
        ?int $previousWorkTypeId
    ): self {
        $this->attached = [];
        $this->detached = [];
        $this->method = $method;

        $now = Alaress_Date_Format::now();

        $dueWork = (new SubmissionBox())
            ->setId(101);

        $assessment = (new DataModel\Model())
            ->setId(201)
            ->setDueWork($dueWork)
            ->setWorkType($currentWorkType)
            ->setOpenDate((clone $now)->addHours(1))
            ->setDueDate((clone $now)->addHours(3))
            ->setCloseDate((clone $now)->addHours(4));

        $plan = (new Model())
            ->setId(301)
            ->setAssessment($assessment)
            ->setIsDynamic(Mapper::PLAN_TYPE_DYNAMIC);

        $diff = [
            'assessment' => [
                'workType' => [$currentWorkType->getId(), $previousWorkTypeId],
            ],
        ];

        $this->event = new Event(
            new User(),
            $method,
            $plan,
            $this->createStub(BaseMapper::class),
            $diff
        );

        return $this;
    }
}
