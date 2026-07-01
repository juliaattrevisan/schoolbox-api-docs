<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades\Test;

use Alaress_Date_Format;
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

    /**
     * A parent viewer must not see an activity whose work type is not visible to parents, even though
     * the student (viewee) participates in it.
     */
    public function testParentViewExcludesActivityWhoseWorkTypeIsNotVisibleToParents(): void
    {
        $viewee = (new User())->setId(self::VIEWEE_ID)->setRole((new Role())->setType(Role::STUDENT));
        $parent = (new User())->setId(self::PARENT_ID)->setRole((new Role())->setType(Role::PARENT));
        $parent->setChildren((new GenericCollection(User::class))->setOffset($viewee->getId(), $viewee));

        $workTypeNotVisibleToParents = (new WorkType\Model())
            ->setIsVisibleToParents(false)
            ->setIsVisibleToLearners(true);

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

        $workTypeNotVisibleToParents = (new WorkType\Model())
            ->setIsVisibleToParents(false)
            ->setIsVisibleToLearners(true);

        [$helper, $term] = $this->buildHelper($workTypeNotVisibleToParents, $viewee, $staff);

        [, , $assessmentsPerFolder] = $helper->generateGrades($staff, $viewee, $term, true);

        self::assertContains(
            self::ASSESSMENT_ID,
            $this->assessmentIds($assessmentsPerFolder),
            'Staff view must still include the activity (staff bypass ignores work-type audience).'
        );
    }

    /**
     * Wires a Helper whose mappers return a single folder containing a single participating, published,
     * open DueWork with the supplied work type. Access is stubbed so the viewee is a learner-participant
     * and the parent is a parent of that learner.
     *
     * @return array{Helper, Term}
     */
    private function buildHelper(WorkType\Model $workType, User $viewee, User $viewer): array
    {
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

        $assessable = $this->buildAssessable($folder, $workType, $viewee, $now);

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

        $helper = new Helper($folderMapper, $mapperProxy, $this->buildAccessStub($viewee, $viewer), $markSchemeFactory);

        return [$helper, $term];
    }

    /**
     * Builds a published, open DueWork in $folder with $viewee as a participant.
     */
    private function buildAssessable(
        Folder\Model $folder,
        WorkType\Model $workType,
        User $viewee,
        Alaress_Date_Format $now
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
            ->setMarkScheme(new None()); // Non-quantitative scheme keeps Markbook statistics trivial.

        $plan = (new Plan($assessable))->setIsDynamic(PlanMapper::PLAN_TYPE_DYNAMIC);
        $plan->getParticipants()[$viewee->getId()] = new Participant($viewee, $plan, ResultCache\Mapper::getInstance());
        $assessable->setPlans($assessable->getPlans()->emptyClone()->add($plan));

        return $assessable;
    }

    /**
     * Stubs Access so the viewee is a learner of the folder and $viewer (if a parent) is the parent of
     * that learner. Nobody is an instructor.
     */
    private function buildAccessStub(User $viewee, User $viewer): Access
    {
        $access = $this->createStub(Access::class);
        $access->method('isInstructor')->willReturn(false);
        $access->method('isLearner')->willReturnCallback(
            static fn (User $user, Folder\Model $folder): bool => $user->getId() === $viewee->getId()
        );
        $access->method('getChildLearnersOfParent')->willReturnCallback(
            static fn (User $user, Folder\Model $folder): GenericCollection => $user->getId() === $viewer->getId()
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
