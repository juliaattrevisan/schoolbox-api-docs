<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed\Test;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Alaress_Date_Format;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\DueWork\Feedback\MapperProxy as DueWorkFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Learning\Assessment\Quiz\Feedback\MapperProxy as QuizFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizFeedback;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Badge\Award\Mapper as BadgeAwardMapper;
use Schoolbox\Learning\Moment\Feed\Cursor;
use Schoolbox\Learning\Moment\Feed\Hydrator;
use Schoolbox\Learning\Moment\Mapper as EvidenceMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Tests Activity Feed feedback hydration against real assessment visibility rules.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(Hydrator::class)]
class HydratorTest extends TestCase
{
    private const ASSESSMENT_ID = 101;

    private const FEEDBACK_ID = 201;

    /**
     * Ensures hydration exposes only feedback permitted by participation, activity audience, and Feedback Visibility.
     *
     * @param array{
     *     discriminator: string,
     *     viewer: 'student'|'other student'|'parent'|'staff',
     *     linkedParent: bool,
     *     participant: bool,
     *     published: bool,
     *     visibleToLearners: bool,
     *     visibleToParents: bool,
     *     markVisibility: string
     * } $scenario Feed visibility inputs.
     * @param bool $expected Whether the feedback row should survive hydration.
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerFeedbackVisibility')]
    public function testFeedbackVisibilityDuringHydration(array $scenario, bool $expected): void
    {
        $viewee = (new User())->setId(301)->setRole((new Role())->setType(Role::STUDENT));
        $viewer = match ($scenario['viewer']) {
            'student' => $viewee,
            'other student' => (new User())->setId(302)->setRole((new Role())->setType(Role::STUDENT)),
            'parent' => (new User())->setId(303)->setRole((new Role())->setType(Role::PARENT)),
            'staff' => (new User())->setId(304)->setRole((new Role())->setType(Role::STAFF)),
        };

        if ($viewer->getRole()->isParent()) {
            $children = new GenericCollection(User::class);
            if ($scenario['linkedParent']) {
                $children->setOffset($viewee->getId(), $viewee);
            }
            $viewer->setChildren($children);
        }

        $folder = (new Folder\Model())->setId(401);
        $assessment = $this->buildAssessment($scenario, $folder, $viewee);
        $feedback = $this->buildFeedback($scenario['discriminator']);
        [$hydrator, $access] = $this->buildHydrator($assessment, $feedback, $viewer, $viewee);

        $hydrated = $hydrator->hydrateResults(new Collection([[
            'discriminator' => $scenario['discriminator'],
            'id' => self::FEEDBACK_ID,
        ]]));

        if (!$expected) {
            self::assertCount(0, $hydrated);
            return;
        }

        self::assertCount(1, $hydrated);
        self::assertSame($scenario['discriminator'], $hydrated->first()['discriminator']);
        self::assertSame($feedback, $hydrated->first()['object']);
        self::assertSame($assessment, $feedback->getAssessable());

        if (!$viewer->getRole()->isStaff()) {
            self::assertFalse(
                $access->isLearner($viewee, $folder),
                'Historical feedback must remain visible while the viewee lacks current folder READ access.'
            );
        }
    }

    /**
     * Provides allowed historical feedback paths and the corresponding security denials for both feedback types.
     *
     * @return array<string, array{array{
     *     discriminator: string,
     *     viewer: 'student'|'other student'|'parent'|'staff',
     *     linkedParent: bool,
     *     participant: bool,
     *     published: bool,
     *     visibleToLearners: bool,
     *     visibleToParents: bool,
     *     markVisibility: string
     * }, bool}>
     */
    public static function providerFeedbackVisibility(): array
    {
        $dueWorkStudent = self::visibleScenario(Cursor::DISCRIMINATOR_DUE_WORK_FEEDBACK);
        $quizStudent = self::visibleScenario(Cursor::DISCRIMINATOR_QUIZ_FEEDBACK);

        return [
            'former student Due Work feedback' => [$dueWorkStudent, true],
            'former student Quiz feedback' => [$quizStudent, true],
            'linked parent Due Work feedback' => [[
                ...$dueWorkStudent,
                'viewer' => 'parent',
                'linkedParent' => true,
            ], true],
            'linked parent Quiz feedback' => [[
                ...$quizStudent,
                'viewer' => 'parent',
                'linkedParent' => true,
            ], true],
            'another student' => [[...$dueWorkStudent, 'viewer' => 'other student'], false],
            'unlinked parent' => [[...$quizStudent, 'viewer' => 'parent'], false],
            'learner-hidden activity' => [[...$dueWorkStudent, 'visibleToLearners' => false], false],
            'parent-hidden activity' => [[
                ...$quizStudent,
                'viewer' => 'parent',
                'linkedParent' => true,
                'visibleToParents' => false,
            ], false],
            'staff-only Due Work feedback for student' => [[
                ...$dueWorkStudent,
                'markVisibility' => IAssessable::MARK_VISIBILITY_PRIVATE,
            ], false],
            'staff-only Quiz feedback for parent' => [[
                ...$quizStudent,
                'viewer' => 'parent',
                'linkedParent' => true,
                'markVisibility' => IAssessable::MARK_VISIBILITY_PRIVATE,
            ], false],
            'missing participant' => [[...$dueWorkStudent, 'participant' => false], false],
            'unpublished assessment' => [[...$quizStudent, 'published' => false], false],
            'staff Due Work feedback' => [[...$dueWorkStudent, 'viewer' => 'staff'], true],
            'staff Quiz feedback' => [[...$quizStudent, 'viewer' => 'staff'], true],
        ];
    }

    /**
     * Builds the default historical Student scenario for one feedback discriminator.
     *
     * @param string $discriminator Feed feedback type under test.
     * @return array{
     *     discriminator: string,
     *     viewer: 'student',
     *     linkedParent: false,
     *     participant: true,
     *     published: true,
     *     visibleToLearners: true,
     *     visibleToParents: true,
     *     markVisibility: string
     * }
     */
    private static function visibleScenario(string $discriminator): array
    {
        return [
            'discriminator' => $discriminator,
            'viewer' => 'student',
            'linkedParent' => false,
            'participant' => true,
            'published' => true,
            'visibleToLearners' => true,
            'visibleToParents' => true,
            'markVisibility' => IAssessable::MARK_VISIBILITY_PARENT_STUDENT,
        ];
    }

    /**
     * Builds a real Due Work or Quiz with the participant and visibility state used by hydration.
     *
     * @param array{
     *     discriminator: string,
     *     viewer: string,
     *     linkedParent: bool,
     *     participant: bool,
     *     published: bool,
     *     visibleToLearners: bool,
     *     visibleToParents: bool,
     *     markVisibility: string
     * } $scenario Feed visibility inputs.
     * @param Folder\Model $folder Assessment context whose current access is denied by the fixture.
     * @param User $viewee Student whose historical participant record anchors access.
     * @return IAssessable
     */
    private function buildAssessment(array $scenario, Folder\Model $folder, User $viewee): IAssessable
    {
        $publishedAt = new Alaress_Date_Format(
            $scenario['published'] ? '2000-01-01 00:00:00' : '2100-01-01 00:00:00',
            Alaress_Date_Format::DATABASE_FORMAT
        );
        $openAt = new Alaress_Date_Format('2000-01-02 00:00:00', Alaress_Date_Format::DATABASE_FORMAT);
        $dueAt = new Alaress_Date_Format('2099-01-01 00:00:00', Alaress_Date_Format::DATABASE_FORMAT);

        $assessment = $scenario['discriminator'] === Cursor::DISCRIMINATOR_DUE_WORK_FEEDBACK
            ? (new DueWork\Model())->setAcceptsMultiple()
            : new Quiz\Model();
        $assessment
            ->setId(self::ASSESSMENT_ID)
            ->setTitle('Historical feedback assessment')
            ->setContext($folder)
            ->setPublishAt($publishedAt)
            ->setOpenAt($openAt)
            ->setDueAt($dueAt)
            ->setCloseAt($dueAt)
            ->setWorkType(
                (new WorkType\Model())
                    ->setIsVisibleToLearners($scenario['visibleToLearners'])
                    ->setIsVisibleToParents($scenario['visibleToParents'])
            )
            ->setMarkVisibility($scenario['markVisibility']);

        $plan = (new Plan($assessment))->setIsDynamic(PlanMapper::PLAN_TYPE_DYNAMIC);
        if ($scenario['participant']) {
            $plan->getParticipants()[$viewee->getId()] = new Participant(
                $viewee,
                $plan,
                ResultCache\Mapper::getInstance()
            );
        }
        $assessment->setPlans($assessment->getPlans()->emptyClone()->add($plan));

        return $assessment;
    }

    /**
     * Builds a feedback object carrying the lightweight assessment reference returned by the feedback mapper.
     *
     * @param string $discriminator Feed feedback type under test.
     * @return DueWorkFeedback|QuizFeedback
     */
    private function buildFeedback(string $discriminator): DueWorkFeedback|QuizFeedback
    {
        $assessmentReference = $discriminator === Cursor::DISCRIMINATOR_DUE_WORK_FEEDBACK
            ? (new DueWork\Model())->setId(self::ASSESSMENT_ID)
            : (new Quiz\Model())->setId(self::ASSESSMENT_ID);
        $feedback = $discriminator === Cursor::DISCRIMINATOR_DUE_WORK_FEEDBACK
            ? new DueWorkFeedback()
            : new QuizFeedback();

        return $feedback
            ->setId(self::FEEDBACK_ID)
            ->setAssessable($assessmentReference);
    }

    /**
     * Builds a Hydrator with mapper stubs and the real visibility service used by production hydration.
     *
     * @param IAssessable $assessment Fully hydrated assessment returned by the assessment mapper.
     * @param DueWorkFeedback|QuizFeedback $feedback Feedback returned for the raw feed row.
     * @param User $viewer Effective Activity Feed viewer.
     * @param User $viewee Profile student whose participant record anchors historical access.
     * @return array{Hydrator, Access&Stub}
     */
    private function buildHydrator(
        IAssessable $assessment,
        DueWorkFeedback|QuizFeedback $feedback,
        User $viewer,
        User $viewee
    ): array {
        $predicateBuilder = $this->createStub(PredicateBuilder::class);

        $evidenceMapper = $this->createStub(EvidenceMapper::class);
        $evidenceMapper->method('getPredicateBuilder')->willReturn($predicateBuilder);
        $evidenceMapper->method('createCollection')->willReturn(new Collection());

        $badgeAwardMapper = $this->createStub(BadgeAwardMapper::class);
        $badgeAwardMapper->method('getPredicateBuilder')->willReturn($predicateBuilder);
        $badgeAwardMapper->method('createCollection')->willReturn(new Collection());

        $dueWorkFeedbackMapper = $this->createStub(DueWorkFeedbackMapperProxy::class);
        $dueWorkFeedbackMapper->method('findByColumnList')->willReturn(
            $feedback instanceof DueWorkFeedback
                ? new GenericCollection(DueWorkFeedback::class, [$feedback])
                : new GenericCollection(DueWorkFeedback::class)
        );

        $quizFeedbackMapper = $this->createStub(QuizFeedbackMapperProxy::class);
        $quizFeedbackMapper->method('findByColumnList')->willReturn(
            $feedback instanceof QuizFeedback
                ? new GenericCollection(QuizFeedback::class, [$feedback])
                : new GenericCollection(QuizFeedback::class)
        );

        $assessmentMapper = $this->createStub(MapperProxy::class);
        $assessmentMapper->method('findAllByIds')->willReturn(
            new GenericCollection(IAssessable::class, [$assessment])
        );

        /** @var Access&Stub $access */
        $access = $this->createStub(Access::class);
        $access->method('isInstructor')->willReturnCallback(
            static fn (User $user, Folder\Model $folder): bool => $user->getRole()->isStaff()
        );
        $access->method('isLearner')->willReturn(false);
        $access->method('getChildLearnersOfParent')->willReturn(new GenericCollection(User::class));

        return [
            new Hydrator(
                $evidenceMapper,
                $badgeAwardMapper,
                $dueWorkFeedbackMapper,
                $quizFeedbackMapper,
                $assessmentMapper,
                new Visibility($access),
                $viewee,
                $viewer,
            ),
            $access,
        ];
    }
}
