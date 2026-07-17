<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Grades\Helper;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Code;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Tests that Helper::generateGrades() (the Grades summary data source) applies work-type / activity
 * visibility for the effective viewer, so that "view as student/parent" hides activities whose work
 * type excludes that role - mirroring what the subject report page (Controller::postReport) does.
 */
class HelperTest extends TestCase
{
    private const NOW_DATABASE = '2010-01-10 00:00:00'; // Freeze time for repeatable date comparisons.

    private const VIEWEE_ID = 3;

    private const PARENT_ID = 4;

    private const STAFF_ID = 1;

    private const ASSESSMENT_ID = 10;

    private const WORK_TYPE_ID = 20;

    /**
     * A parent viewer must not see an activity whose work type is not visible to parents, even though
     * the student (viewee) participates in it.
     */
    public function testParentViewExcludesActivityWhoseWorkTypeIsNotVisibleToParents(): void
    {
        $viewee = (new User())->setId(self::VIEWEE_ID)->setRole((new Role())->setType(Role::STUDENT));
        $parent = (new User())->setId(self::PARENT_ID)->setRole((new Role())->setType(Role::PARENT));
        $parent->setChildren((new GenericCollection(User::class))->setOffset($viewee->getId(), $viewee));

        $workTypeNotVisibleToParents = $this->buildWorkType(false, true);

        [$helper, $term] = $this->buildHelper($workTypeNotVisibleToParents, $viewee, $parent);

        [, , $assessmentsPerFolder] = $helper->generateGrades($parent, $viewee, $term, false);

        self::assertNotContains(
            self::ASSESSMENT_ID,
            $this->assessmentIds($assessmentsPerFolder),
            'Parent view must exclude an activity whose work type is not visible to parents.'
        );
    }

    /**
     * A staff viewer must still see the same activity: the staff branch of the visibility rules ignores
     * work-type audience restrictions. This guards against an over-broad fix that filters for everyone.
     */
    public function testStaffViewIncludesActivityWhoseWorkTypeIsNotVisibleToParents(): void
    {
        $viewee = (new User())->setId(self::VIEWEE_ID)->setRole((new Role())->setType(Role::STUDENT));
        $staff  = (new User())->setId(self::STAFF_ID)->setRole((new Role())->setType(Role::STAFF));

        $workTypeNotVisibleToParents = $this->buildWorkType(false, true);

        [$helper, $term] = $this->buildHelper($workTypeNotVisibleToParents, $viewee, $staff);

        [, , $assessmentsPerFolder] = $helper->generateGrades($staff, $viewee, $term, true);

        self::assertContains(
            self::ASSESSMENT_ID,
            $this->assessmentIds($assessmentsPerFolder),
            'Staff view must still include the activity (staff bypass ignores work-type audience).'
        );
    }

    /**
     * Historical grades are allowed from the student's recorded participation, but still respect audience and feedback.
     *
     * @param string $viewerRoleType Role used by the effective viewer.
     * @param bool $viewerIsParent Whether to hydrate the effective viewer as the viewee's parent.
     * @param bool $workTypeVisibleToParents Whether the activity is visible to parents.
     * @param bool $workTypeVisibleToLearners Whether the activity is visible to learners.
     * @param string $markVisibility Feedback visibility configured on the assessment.
     * @param bool $expected Whether the historical grade should remain visible.
     * @return void
     */
    #[DataProvider('providerHistoricalGradeVisibility')]
    public function testHistoricalGradeVisibility(
        string $viewerRoleType,
        bool $viewerIsParent,
        bool $workTypeVisibleToParents,
        bool $workTypeVisibleToLearners,
        string $markVisibility,
        bool $expected
    ): void {
        $viewee = (new User())->setId(self::VIEWEE_ID)->setRole((new Role())->setType(Role::STUDENT));
        $viewer = (new User())->setId($viewerIsParent ? self::PARENT_ID : self::VIEWEE_ID)
            ->setRole((new Role())->setType($viewerRoleType));

        if ($viewerIsParent) {
            $viewer->setChildren((new GenericCollection(User::class))->setOffset($viewee->getId(), $viewee));
        }

        $workType = $this->buildWorkType($workTypeVisibleToParents, $workTypeVisibleToLearners);

        [$helper, $term] = $this->buildHelper(
            $workType,
            $viewee,
            $viewer,
            false,
            $markVisibility
        );

        [, , $assessmentsPerFolder] = $helper->generateGrades($viewer, $viewee, $term, false);

        if ($expected) {
            self::assertContains(self::ASSESSMENT_ID, $this->assessmentIds($assessmentsPerFolder));
            return;
        }

        self::assertNotContains(self::ASSESSMENT_ID, $this->assessmentIds($assessmentsPerFolder));
    }

    /**
     * The Grades summary post-filter must keep historical grades when supplied with the viewee.
     *
     * @return void
     */
    public function testFilterAssessmentsUsesViewee(): void
    {
        $viewee = (new User())->setId(self::VIEWEE_ID)->setRole((new Role())->setType(Role::STUDENT));
        $workType = $this->buildWorkType(true, true);

        [$helper, $term] = $this->buildHelper(
            $workType,
            $viewee,
            $viewee,
            false
        );

        [, , $assessmentsPerFolder] = $helper->generateGrades($viewee, $viewee, $term, false);
        $filteredAssessments = $helper->filterAssessments(
            $viewee,
            $assessmentsPerFolder,
            new GenericCollection(WorkType\Model::class, [$workType]),
            null,
            $viewee
        );

        self::assertContains(self::ASSESSMENT_ID, $this->assessmentIds($filteredAssessments));
    }

    /**
     * Provides visible and hidden historical grade cases after current folder READ access has been removed.
     *
     * @return array<string, array{string, bool, bool, bool, string, bool}>
     */
    public static function providerHistoricalGradeVisibility(): array
    {
        return [
            'Valid former student participant' => [
                Role::STUDENT,
                false,
                true,
                true,
                IAssessable::MARK_VISIBILITY_PARENT_STUDENT,
                true,
            ],
            'Valid parent of former student participant' => [
                Role::PARENT,
                true,
                true,
                true,
                IAssessable::MARK_VISIBILITY_PARENT_STUDENT,
                true,
            ],
            'Invalid former student hidden by learner audience' => [
                Role::STUDENT,
                false,
                true,
                false,
                IAssessable::MARK_VISIBILITY_PARENT_STUDENT,
                false,
            ],
            'Invalid former student hidden by staff-only feedback' => [
                Role::STUDENT,
                false,
                true,
                true,
                IAssessable::MARK_VISIBILITY_PRIVATE,
                false,
            ],
            'Invalid parent hidden by parent audience' => [
                Role::PARENT,
                true,
                false,
                true,
                IAssessable::MARK_VISIBILITY_PARENT_STUDENT,
                false,
            ],
            'Invalid parent hidden by student-only feedback' => [
                Role::PARENT,
                true,
                true,
                true,
                IAssessable::MARK_VISIBILITY_STUDENT,
                false,
            ],
        ];
    }

    /**
     * Builds a work type with a stable id so grade-page work type filtering can match it.
     *
     * @param bool $visibleToParents Whether parent viewers may see activities with this work type.
     * @param bool $visibleToLearners Whether learner viewers may see activities with this work type.
     * @return WorkType\Model
     */
    private function buildWorkType(bool $visibleToParents, bool $visibleToLearners): WorkType\Model
    {
        return (new WorkType\Model())
            ->setId(self::WORK_TYPE_ID)
            ->setIsVisibleToParents($visibleToParents)
            ->setIsVisibleToLearners($visibleToLearners);
    }

    /**
     * Wires a Helper whose mappers return a single folder containing a single participating, published,
     * open DueWork with the supplied work type. Access is stubbed so the viewee is a learner-participant
     * and the parent is a parent of that learner.
     *
     * @param WorkType\Model $workType Activity audience used by the fixture assessment.
     * @param User $viewee Student whose grade history is being viewed.
     * @param User $viewer Effective viewer whose visibility rules are being tested.
     * @param bool $vieweeIsLearner Whether current folder READ access should be granted to the viewee.
     * @param string $markVisibility Feedback visibility used by the fixture assessment.
     * @return array{Helper, Term}
     */
    private function buildHelper(
        WorkType\Model $workType,
        User $viewee,
        User $viewer,
        bool $vieweeIsLearner = true,
        string $markVisibility = IAssessable::MARK_VISIBILITY_PARENT_STUDENT
    ): array {
        $now = new Alaress_Date_Format(self::NOW_DATABASE, Alaress_Date_Format::DATABASE_FORMAT);

        // generateGrades() calls $folder->loadTeachers(), which reaches an external class / DB lookup
        // unavailable in the unit test harness. Use a real folder that overrides just that method.
        $folder = new class extends Folder\Model {
            public function loadTeachers($includeSynergy = true): static
            {
                return $this;
            }
        };
        $folder->setId(1)->setName('A Subject');
        $folder->setCodes(new GenericCollection(Code\Model::class));

        $assessable = $this->buildAssessable($folder, $workType, $viewee, $now, $markVisibility);

        $term = $this->createStub(Term::class);
        $term->method('getStart')->willReturn((clone $now)->addDays(-30));
        $term->method('getActualFinish')->willReturn((clone $now)->addDays(30));

        $folderMapper = $this->createStub(FolderMapper::class);
        $folderMapper->method('findFoldersWithInteractionsForStudent')
            ->willReturn(new GenericCollection(Folder\Model::class, [$folder]));
        $folderMapper->method('filterIdsForGradePage')->willReturn([$folder->getId()]);

        $assessments = new GenericCollection(IAssessable::class, [$assessable]);
        $mapperProxy = $this->createStub(AssessmentMapperProxy::class);
        $mapperProxy->method('findAllByStudentBetweenDates')->willReturn($assessments);
        // Participants are pre-attached to the fixture assessable, so just hand the collection back.
        $mapperProxy->method('loadParticipantsIntoAssessmentCollection')->willReturnArgument(0);

        $markSchemeFactory = $this->createStub(MarkSchemeFactory::class);
        $markSchemeFactory->method('spawnForFolder')->willReturn(new None());

        $helper = new Helper(
            $folderMapper,
            $mapperProxy,
            $this->buildAccessStub($viewee, $viewer, $vieweeIsLearner),
            $markSchemeFactory
        );

        return [$helper, $term];
    }

    /**
     * Builds a published, open DueWork in $folder with $viewee as a participant.
     *
     * @param Folder\Model $folder Folder used as the assessment context.
     * @param WorkType\Model $workType Activity audience used by the fixture assessment.
     * @param User $viewee Student participant attached to the fixture assessment.
     * @param Alaress_Date_Format $now Fixed date used to derive publish and close dates.
     * @param string $markVisibility Feedback visibility used by the fixture assessment.
     * @return DueWork\Model
     */
    private function buildAssessable(
        Folder\Model $folder,
        WorkType\Model $workType,
        User $viewee,
        Alaress_Date_Format $now,
        string $markVisibility
    ): DueWork\Model {
        $assessable = (new DueWork\Model())
            ->setId(self::ASSESSMENT_ID)
            ->setTitle('A DueWork')
            ->setContext($folder)
            ->setAcceptsMultiple() // Avoid due/close date magic in DueWork::getCloseAt().
            ->setPublishAt((clone $now)->addDays(-2))
            ->setOpenAt((clone $now)->addDays(-1))
            ->setDueAt((clone $now)->addDays(1))
            ->setCloseAt((clone $now)->addDays(2))
            ->setWorkType($workType)
            ->setMarkVisibility($markVisibility)
            ->setMarkScheme(new None()); // Non-quantitative scheme keeps Markbook statistics trivial.

        $plan = (new Plan($assessable))->setIsDynamic(PlanMapper::PLAN_TYPE_DYNAMIC);
        $plan->getParticipants()[$viewee->getId()] = new Participant($viewee, $plan, ResultCache\Mapper::getInstance());
        $assessable->setPlans($assessable->getPlans()->emptyClone()->add($plan));

        return $assessable;
    }

    /**
     * Stubs Access so the viewee is a learner of the folder and $viewer (if a parent) is the parent of
     * that learner. Nobody is an instructor.
     *
     * @param User $viewee Student whose current learner access is controlled by the stub.
     * @param User $viewer Effective viewer used by parent access callbacks.
     * @param bool $vieweeIsLearner Whether current folder READ access should be granted to the viewee.
     * @return Access
     */
    private function buildAccessStub(User $viewee, User $viewer, bool $vieweeIsLearner): Access
    {
        $access = $this->createStub(Access::class);
        $access->method('isInstructor')->willReturn(false);
        $access->method('isLearner')->willReturnCallback(
            static fn (User $user, Folder\Model $folder): bool => $vieweeIsLearner
                && $user->getId() === $viewee->getId()
        );
        $access->method('getChildLearnersOfParent')->willReturnCallback(
            static fn (User $user, Folder\Model $folder): GenericCollection => $vieweeIsLearner
                && $user->getId() === $viewer->getId()
                ? new GenericCollection(User::class, [$viewee])
                : new GenericCollection(User::class)
        );

        return $access;
    }

    /**
     * Flattens the per-folder assessment collection returned by generateGrades() to a list of ids.
     *
     * @return int[]
     */
    private function assessmentIds(iterable $assessmentsPerFolder): array
    {
        $ids = [];
        foreach ($assessmentsPerFolder as $assessments) {
            foreach ($assessments as $assessment) {
                $ids[] = $assessment->getId();
            }
        }

        return $ids;
    }
}
