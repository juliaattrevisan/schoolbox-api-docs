<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\ImportStage;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use External\Canvas\Sync\Bridge;
use External\Canvas\Sync\ImportStage\Traits\ModelSaveTrait;
use External_ClassInterface;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Assessment\Participant;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\Quiz;
use Schoolbox\Learning\Quiz\Section as QuizSection;
use Schoolbox\Learning\Quiz\Section\Question as QuizQuestion;
use Schoolbox\Learning\Quiz\Section\Question\QuestionType;
use Schoolbox\Learning\Quiz\Section\Question\Solution as QuizSolution;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;
use function array_key_exists;
use function array_map;
use function array_merge;
use function count;
use function implode;
use function reset;

/**
 * Responsible for importing Class Quizzes.
 *
 * Uses Canvas Section + Quiz + Override data to generate Schoolbox QuizImpl, Plans + Participants
 */
class SectionQuiz extends SectionAssessmentAbstract
{
    use ModelSaveTrait;

    /** @var IConnector */
    private $canvasDb;

    /** @var IConnector */
    private $schoolboxDb;

    /** @var Quiz\Mapper */
    private $quizMapper;

    /** @var QuizSection\Mapper */
    private $quizSectionMapper;

    /** @var QuizQuestion\Mapper */
    private $quizQuestionMapper;

    /** @var QuestionType\Mapper */
    private $questionTypeMapper;

    /** @var QuizSolution\Mapper */
    private $quizSolutionMapper;

    /** @var Assessment\Mapper */
    private $assessmentMapper;

    /** @var Participant\Plan\Mapper */
    private $participantPlanMapper;

    /** @var LoggerInterface */
    private $log;


    /** @var Quiz\Model[] $quizSpecCache Indexed by Canvas `assess`.`canvas_id` */
    private $quizSpecCache = [];

    /** @var int */
    private $countQuizSectionsCreated;

    /** @var int */
    private $countQuizSectionsDeleted;


    /**
     * @param IConnector                $canvasDb
     * @param IConnector                $schoolboxDb
     * @param External_ClassInterface   $externalClass
     * @param string|null               $currentFileYear
     * @param UnitImpl\Mapper           $unitImplMapper
     * @param UnitSpec\Mapper           $unitSpecMapper
     * @param Quiz\Mapper               $quizMapper
     * @param QuizSection\Mapper        $quizSectionMapper
     * @param QuizQuestion\Mapper       $quizQuestionMapper
     * @param QuizSolution\Mapper       $quizSolutionMapper
     * @param Assessment\Mapper         $assessmentMapper
     * @param Participant\Plan\Mapper   $planMapper
     * @param Participant\Mapper        $participantMapper
     * @param UserMapper                $userMapper
     * @param LoggerInterface           $log
     * @param Bridge\AssignmentDates    $assignmentDatesBridge
     * @param Bridge\Course             $courseBridge
     * @param Bridge\CourseSection      $courseSectionBridge
     * @param Bridge\AssignmentOverride $assignmentOverrideBridge
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        External_ClassInterface $externalClass,
        ?string $currentFileYear,
        UnitImpl\Mapper $unitImplMapper,
        UnitSpec\Mapper $unitSpecMapper,
        Quiz\Mapper $quizMapper,
        QuizSection\Mapper $quizSectionMapper,
        QuizQuestion\Mapper $quizQuestionMapper,
        QuizSolution\Mapper $quizSolutionMapper,
        Assessment\Mapper $assessmentMapper,
        Participant\Plan\Mapper $planMapper,
        Participant\Mapper $participantMapper,
        UserMapper $userMapper,
        LoggerInterface $log,
        Bridge\AssignmentDates $assignmentDatesBridge,
        Bridge\Course $courseBridge,
        Bridge\CourseSection $courseSectionBridge,
        Bridge\AssignmentOverride $assignmentOverrideBridge
    ) {
        parent::__construct(
            $canvasDb,
            $schoolboxDb,
            $externalClass,
            $currentFileYear,
            $unitImplMapper,
            $unitSpecMapper,
            $assessmentMapper,
            $planMapper,
            $participantMapper,
            $userMapper,
            $log,
            $assignmentDatesBridge,
            $courseBridge,
            $courseSectionBridge,
            $assignmentOverrideBridge
        );
        $this->canvasDb              = $canvasDb;
        $this->schoolboxDb           = $schoolboxDb;
        $this->quizMapper            = $quizMapper;
        $this->quizSectionMapper     = $quizSectionMapper;
        $this->quizQuestionMapper    = $quizQuestionMapper;
        $this->quizSolutionMapper    = $quizSolutionMapper;
        $this->participantPlanMapper = $planMapper;
        $this->log                   = $log;
    }


    /** @inheritDoc */
    protected function getCanvasEntity(): string
    {
        return 'quiz';
    }


    /** @inheritDoc */
    protected function getSchoolboxEntity(): string
    {
        return 'quiz';
    }


    /** @inheritDoc */
    protected function loadCanvasAssessmentAndOverrideData(array $sectionSisSourceIds): array
    {
        if (!count($sectionSisSourceIds)) {
            return [];
        }
        $quizCanvasIds = $this->getQuizCanvasIds();
        if (!count($quizCanvasIds)) {
            return [];
        }
        $quizCanvasIdBindings = Builder::getBindings($quizCanvasIds, 'q_cid');

        $mySqlQuoter = new Database\Quoter\Mysql\Quoter();
        $pb          = (new Builder($mySqlQuoter, ''))->in('section.sis_source_id', $sectionSisSourceIds);
        $whereClause = implode(' AND ', $pb->getWhere());

        $sql       = "
            SELECT
                course.canvas_id                                    'course.canvas_id',
                quiz.canvas_id                                      'assessable.canvas_id',
                quiz.name                                           'assessable.title',
                COALESCE(assignment.visibility, 'everyone')         'assessable.visibility',       -- TODO, confirm this logic via functional testing.
                CONVERT_TZ(quiz.unlock_at, 'UTC', 'SYSTEM')         'assessable.unlock_at',
                CONVERT_TZ(quiz.lock_at, 'UTC', 'SYSTEM')           'assessable.lock_at',
                CONVERT_TZ(quiz.due_at, 'UTC', 'SYSTEM')            'assessable.due_at',
                assignment.all_day                                  'assessable.all_day',
                assignment.all_day_date                             'assessable.all_day_date',
                override.canvas_id                                  'override.canvas_id',
                override.title                                      'override.title',
                CONVERT_TZ(override.unlock_at, 'UTC', 'SYSTEM')     'override.unlock_at',
                CONVERT_TZ(override.lock_at, 'UTC', 'SYSTEM')       'override.lock_at',
                CONVERT_TZ(override.due_at, 'UTC', 'SYSTEM')        'override.due_at',
                override.all_day                                    'override.all_day',
                override.all_day_date                               'override.all_day_date',
                override_section.canvas_id                          'override_section.canvas_id',
                override_section.sis_source_id                      'override_section.sis_source_id',
                override_group.canvas_id                            'override_group.canvas_id',
                override_group.name                                 'override_group.name',
                group_pseudonym.canvas_id                           'group_pseudonym.canvas_id',
                group_pseudonym.sis_user_id                         'group_pseudonym.sis_user_id',
                user_pseudonym.canvas_id                            'user_pseudonym.canvas_id',
                user_pseudonym.sis_user_id                          'user_pseudonym.sis_user_id'
            FROM course_dim course
            JOIN course_section_dim section ON course.id = section.course_id
            LEFT JOIN quiz_dim quiz ON quiz.course_id = section.course_id AND quiz.canvas_id IN ({$quizCanvasIdBindings->getPlaceholder()})
            LEFT JOIN assignment_dim assignment ON assignment.id = quiz.assignment_id
            LEFT JOIN assignment_override_dim override ON override.quiz_id = quiz.id AND override.workflow_state = 'active' AND override.set_type IS NOT NULL -- (master paths have set_type = NULL, not yet supported)
            -- course sections
            LEFT JOIN course_section_dim override_section ON override.course_section_id = override_section.id AND override.set_type = 'course_section'
            -- course groups
            LEFT JOIN group_dim override_group ON override_group.id = override.group_id AND override.set_type = 'group'
            LEFT JOIN group_membership_fact group_membership ON group_membership.group_id = override_group.id
            LEFT JOIN pseudonym_dim group_pseudonym ON group_pseudonym.user_id = group_membership.user_id
            -- individual assignees
            LEFT JOIN assignment_override_user_dim override_user ON override_user.assignment_override_id = override.id AND override.set_type = 'adhoc'
            LEFT JOIN pseudonym_dim user_pseudonym ON user_pseudonym.user_id = override_user.user_id
            WHERE {$whereClause}
            ORDER BY course.code, course.canvas_id, section.canvas_id, quiz.canvas_id, override.canvas_id
        ";
        $statement = $this->canvasDb->executeQuery($sql, array_merge($pb->getParams(), $quizCanvasIdBindings->getParams()));

        return $this->buildCanvasOverrideData($statement);
    }


    /**
     * Returns a quiz spec with sections, questions + solutions hydrated
     * @inheritDoc
     * @throws UnexpectedValueException
     */
    protected function findAssessableSpec(int $assessmentCanvasId): IAssessableDataModel
    {
        // Because Canvas Assignments (SB SubBox) belong to a Course, rather than CourseSection (SB Class),
        // we either need to either loop assignment per class, or classes per assignment.
        // Eg, if fetching assignments inside a class, we don't want to hit the DB next time we process the same logical
        // assignment in a different class.
        // Therefore cache the assignment fetch so subsequent class iterations don't hit the db.
        if (!array_key_exists($assessmentCanvasId, $this->quizSpecCache)) {
            $quiz = $this->quizMapper->findOneByColumn('external_id', $assessmentCanvasId);
            $quiz->getAssessmentModel()->setQuiz($quiz);
            $indexSectionsById = static function (QuizSection\Model $section): int {
                return $section->getId();
            };
            $sections          = $this->quizSectionMapper
                ->findByColumn('assess_id', $quiz->getId())
                ->indexByCallback($indexSectionsById);
            $quiz->setSections($sections);
            $questions = $this->quizQuestionMapper->findByColumn('section_id', $quiz->getSections()->keys());
            foreach ($questions as $question) {
                /** @var QuizSection\Model $section */
                $section = $quiz->getSections()->getOffset($question->getSection()->getId());
                $section->getQuestions()->setOffset($question->getId(), $question);
                $question->setSection($section);
            }
            $this->quizSpecCache[$assessmentCanvasId] = $quiz;
        }

        return $this->quizSpecCache[$assessmentCanvasId];
    }


    /**
     * @inheritDoc
     * @param IAssessableDataModel|Quiz\Model $assessable
     */
    protected function getAssessableOwner(IAssessableDataModel $assessable): User
    {
        return $assessable->getUser();
    }


    /**
     * @inheritDoc
     * @param IAssessableDataModel|Quiz\Model $assessableImpl
     * @param IAssessableDataModel|Quiz\Model $assessableSpec
     */
    protected function checkAssessableForUpdates(
        int $assessmentCanvasId,
        IAssessableDataModel $assessableImpl,
        IAssessableDataModel $assessableSpec,
        User $owner,
        UnitImpl\Model $unitImpl
    ): bool {
        $quizUpdated    = $this->syncWithQuiz($assessmentCanvasId, $assessableImpl, $assessableSpec, $owner, $unitImpl);
        $sectionUpdated = $this->syncSections($assessableImpl, $assessableSpec);

        return $quizUpdated || $sectionUpdated;
    }

    /**
     * Checks whether the Quiz Spec & Quiz Impl sections (including sub-models) are the same.
     *
     * If not, deletes any impl sections and re-copies from spec
     *
     * @param Quiz\Model $quizImpl
     * @param Quiz\Model $quizSpec
     * @return bool
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function syncSections(Quiz\Model $quizImpl, Quiz\Model $quizSpec): bool
    {
        $indexBySequence = static function (QuizSection\Model $section): int {
            return $section->getSequence();
        };

        $implSectionsBySequence = $quizImpl->getSections()->shallowClone()->indexByCallback($indexBySequence);
        $specSectionsBySequence = $quizSpec->getSections()->shallowClone()->indexByCallback($indexBySequence);

        $different = false;
        if ($implSectionsBySequence->keys() !== $specSectionsBySequence->keys()) {
            $different = true;
        }
        if (!$different) {
            foreach ($implSectionsBySequence->keys() as $sequence) {
                if (!$this->areQuizSectionsEqual(
                    $implSectionsBySequence->getOffset($sequence),
                    $specSectionsBySequence->getOffset($sequence)
                )) {
                    $different = true;
                    break;
                }
            }
        }

        if ($different) {
            if ($quizImpl->getSections()->count()) {
                $this->log->debug("Quiz sections different between spec and impl, re-creating from spec");
                $this->quizSectionMapper->delete($quizImpl->getSections());
                $this->countQuizSectionsDeleted += $quizImpl->getSections()->count();
                $quizImpl->setSections($quizImpl->getSections()->emptyClone());
            }
            foreach ($quizSpec->getSections() as $sectionSpec) {
                $this->copySection($sectionSpec, $quizImpl);
            }
        }
        return $different;
    }


    /**
     * Copies a quiz section (and sub models) to another quiz
     *
     * @param QuizSection\Model $section
     * @param Quiz\Model        $quiz
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function copySection(QuizSection\Model $section, Quiz\Model $quiz): void
    {
        $this->log->debug("Copying quiz spec section '{$section->getTitle()}' to quiz impl '{$quiz->getTitle()}'");
        $this->countQuizSectionsCreated++;

        $newSection = clone $section;

        $newSection->setQuiz($quiz);
        $this->quizSectionMapper->save($newSection);
        $quiz->getSections()->setOffset($newSection->getId(), $newSection);

        $newQuestions = $newSection->getQuestions();
        foreach ($newQuestions as $newQuestion) {
            $this->quizQuestionMapper->save($newQuestion);
            $this->quizSolutionMapper->save($newQuestion->getSolution());
        }
    }


    /**
     * Returns whether two quiz sections are logically equal.
     *
     * Must have same attributes and contain same questions in the same sequence
     *
     * @param QuizSection\Model $left
     * @param QuizSection\Model $right
     * @return bool
     */
    private function areQuizSectionsEqual(QuizSection\Model $left, QuizSection\Model $right): bool
    {
        if (
            $left->getDescription() !== $right->getDescription() ||
            $left->getTitle() !== $right->getTitle() ||
            $left->getPool() !== $right->getPool() ||
            $left->getRandom() !== $right->getRandom() ||
            $left->getSequence() !== $right->getSequence() ||
            $left->getQuestions()->count() !== $right->getQuestions()->count()
        ) {
            return false;
        }

        $indexBySequence          = static function (QuizQuestion\Model $question): int {
            return $question->getSequence();
        };
        $leftQuestionsBySequence  = $left->getQuestions()->shallowClone()->indexByCallback($indexBySequence);
        $rightQuestionsBySequence = $right->getQuestions()->shallowClone()->indexByCallback($indexBySequence);

        if ($leftQuestionsBySequence->keys() !== $rightQuestionsBySequence->keys()) {
            return false;
        }

        foreach ($leftQuestionsBySequence->keys() as $sequence) {
            $leftQuestion  = $leftQuestionsBySequence->getOffset($sequence);
            $rightQuestion = $rightQuestionsBySequence->getOffset($sequence);
            if (!$this->areQuizQuestionsEqual($leftQuestion, $rightQuestion)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Returns whether two quiz questions are logically equal.
     *
     * Must have same attributes and contain same solutions in the same sequence
     *
     * @param QuizQuestion\Model $left
     * @param QuizQuestion\Model $right
     * @return bool
     */
    private function areQuizQuestionsEqual(QuizQuestion\Model $left, QuizQuestion\Model $right): bool
    {
        if (
            $left->getConfig() !== $right->getConfig() ||
            $left->getMarks() !== $right->getMarks() ||
            $left->getQuestion() !== $right->getQuestion() ||
            $left->getType()->getId() !== $right->getType()->getId()
        ) {
            return false;
        }
        return $this->areQuizSolutionsEqual($left->getSolution(), $right->getSolution());
    }


    /**
     * Returns whether two quiz solutions are equal.
     *
     * @param QuizSolution\Model $left
     * @param QuizSolution\Model $right
     * @return bool
     */
    private function areQuizSolutionsEqual(QuizSolution\Model $left, QuizSolution\Model $right): bool
    {
        return $left->getSolution() === $right->getSolution();
    }


    /**
     * Checks whether the quiz impl needs to be reset to quiz spec properties. Returns whether any changes made.
     *
     * @param int            $assessmentCanvasId
     * @param Quiz\Model     $quizImpl
     * @param Quiz\Model     $quizSpec
     * @param User           $owner
     * @param UnitImpl\Model $unitImpl
     * @return bool
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    private function syncWithQuiz(
        int $assessmentCanvasId,
        Quiz\Model $quizImpl,
        Quiz\Model $quizSpec,
        User $owner,
        UnitImpl\Model $unitImpl
    ): bool {
        if (
            // Does any attribute no longer align?
            $quizImpl->getAvailability() === Quiz\Model::AVAILABILITY_ASSESS &&
            (($quizImpl->getWorkType() === null) === ($quizSpec->getWorkType() === null)) &&
            $quizImpl->getTitle() === $quizSpec->getTitle() &&
            $quizImpl->getDescription() === $quizSpec->getDescription() &&
            $quizImpl->getDuration() === $quizSpec->getDuration() &&
            $quizImpl->getMaxAttempts() === $quizSpec->getMaxAttempts() &&
            $quizImpl->getWeight() === $quizSpec->getWeight() &&
            $quizImpl->getAnonymousResult() === $quizSpec->getAnonymousResult() &&
            $quizImpl->getMarkVisibility() === $quizSpec->getMarkVisibility() &&
            $quizImpl->getUser()->getId() === $owner->getId() &&
            $quizImpl->getCreated()->getDatabase() === $quizSpec->getCreated()->getDatabase() &&
            $quizImpl->getCategory()->getId() === $unitImpl->getFolder()->getId() &&
            ($quizImpl->getWorkType() && $quizSpec->getWorkType() && $quizImpl->getWorkType()->getId() === $quizSpec->getWorkType()->getId())
        ) {
            return false;
        }
        // Update all attributes and persist
        $this->log->debug("Updating QuizImpl for Canvas Quiz (canvasId: $assessmentCanvasId)");
        $quizImpl
            ->setCategory($unitImpl->getFolder())
            ->setUser($owner)
            ->setWorkType($quizSpec->getWorkType())
            ->setTitle($quizSpec->getTitle())
            ->setDescription($quizSpec->getDescription())
            ->setDuration($quizSpec->getDuration())
            ->setMaxAttempts($quizSpec->getMaxAttempts())
            ->setWeight($quizSpec->getWeight())
            ->setAvailability(Quiz\Model::AVAILABILITY_ASSESS)
            ->setAnonymousResult($quizSpec->getAnonymousResult())
            ->setMarkVisibility($quizSpec->getMarkVisibility())
            ->setCreated($quizSpec->getCreated());

        $this->quizMapper->save($quizImpl);

        return true;
    }


    /**
     * Returns a Quiz Impl for the specified AssessmentSpecId + UnitImplId, or NULL if none found
     * Hydrates:
     *  Quiz
     *  Assessment
     *  Participation Plan (so we don't recreate plan due to mapper pre-save callback) TODO fix this properly in develop
     *  QuizSections
     *  QuizQuestions
     *  QuizSolutions
     *
     * Hydrates:
     *  assess
     *  assess.assessment
     *  assess.assessment.participant_plan (so we don't recreate plan due to mapper pre-save callback) TODO fix this properly in develop
     *  assess.assess_section
     *  assess.assess_section.assess_question
     *  assess.assess_section.assess_question.assess_solution
     *
     * @param Assessment\Model $assessmentSpec
     * @param UnitImpl\Model   $unitImpl
     * @return SubmissionBox|null
     * @throws Database\Exception
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    protected function findAssessableImpl(Assessment\Model $assessmentSpec, UnitImpl\Model $unitImpl): ?IAssessableDataModel
    {
        // Find the quiz impl's id
        $sql     = "
            SELECT q.assess_id
            FROM assess q
            JOIN assessment a ON a.id = q.assessment_id
            JOIN assessment_specification_implementation asi ON a.id = asi.implementation_assessment_id
            JOIN assessment_course_unit_implementation acui ON a.id = acui.assessment_id
            WHERE
                  asi.specification_assessment_id = :aSpecId AND
                  acui.course_unit_implementation_id = :unitImplId AND
                  a.deleted_at IS NULL
        ";
        $quizIds = $this->schoolboxDb->executeQuery($sql, [
            'aSpecId'    => $assessmentSpec->getId(),
            'unitImplId' => $unitImpl->getId(),
        ])->fetchAll(PDO::FETCH_COLUMN);
        if (!count($quizIds)) {
            return null;
        }
        if (count($quizIds) > 1) {
            throw new UnexpectedValueException("Multiple Quiz Impls found for assessmentSpecId {$assessmentSpec->getId()} in UnitImpl {$unitImpl->getId()}");
        }

        $quizImpl = $this->quizMapper->findById(reset($quizIds));
        $sections = $this->quizSectionMapper
            ->findByColumn(QuizSection\Mapper::COL_ASSESS_ID, $quizImpl->getId())
            ->indexByCallback(static function (QuizSection\Model $section): int {
                return $section->getId();
            });

        $questions = $this->quizQuestionMapper->findByColumn(QuizQuestion\Mapper::COL_SECTION_ID, $sections->keys());
        foreach ($questions as $question) {
            /** @var QuizSection\Model $section */
            $section = $sections->getOffset($question->getSection()->getId());
            $section->getQuestions()->setOffset($question->getId(), $question);
            $question->setSection($section);
        }

        $quizImpl->setSections($sections);

        $assessmentImpl = $quizImpl->getAssessmentModel();
        $assessmentImpl->setQuiz($quizImpl);
        $assessmentImpl->setSpecificationAssessment($assessmentSpec);
        $assessmentImpl->setContextUnitImplementation($unitImpl);

        $plans = $this->participantPlanMapper->findByColumn(Participant\Plan\Mapper::COL_ASSESSMENT_ID, $assessmentImpl->getId());
        foreach ($plans as $plan) {
            $plan->setAssessment($assessmentImpl);
        }
        $assessmentImpl->setPlans($plans);

        return $quizImpl;
    }


    /**
     * @param IAssessableDataModel|Quiz\Model $assessableSpec With Sections, Questions + Solutions hydrated
     * @inheritDoc
     */
    protected function createAssessableImpl(
        IAssessableDataModel $assessableSpec,
        UnitImpl\Model $unitImpl,
        User $owner,
        string $publishDate,
        string $openDate,
        string $dueDate,
        string $closeDate
    ): IAssessableDataModel {
        $assessmentImpl = $this->createAssessmentImplFromSpec(
            $assessableSpec->getAssessmentModel(),
            $unitImpl,
            $publishDate,
            $openDate,
            $dueDate,
            $closeDate
        );

        /** @var Quiz\Model $quizImpl */
        $quizImpl = $assessmentImpl->getQuiz();
        $quizImpl
            ->setAssessmentModel($assessmentImpl)            // TODO This line compensates for a core bug. Fix this properly in develop.
            ->setCategory($unitImpl->getFolder())
            ->setUser($owner)
            ->setExternalId(null);

        // Insert Quiz Impl
        $this->quizMapper->save($quizImpl);

        foreach ($assessableSpec->getSections() as $sectionSpec) {
            $this->copySection($sectionSpec, $quizImpl);
        }

        return $quizImpl;
    }


    /** @inheritDoc */
    protected function getAssessableAssessmentIds(): array
    {
        $sql = "
            SELECT aImpl.id
            FROM assess qSpec
            JOIN assessment aSpec ON aSpec.id = qSpec.assessment_id
            JOIN assessment_specification_implementation asi ON aSpec.id = asi.specification_assessment_id
            JOIN assessment aImpl ON aImpl.id = asi.implementation_assessment_id
            WHERE
                  qSpec.external_id IS NOT NULL AND
                  aImpl.deleted_at IS NULL
        ";
        return array_map('intval', $this->schoolboxDb->executeQuery($sql)->fetchAll(PDO::FETCH_COLUMN));
    }


    /** @inheritDoc */
    protected function getCandidatePlanIds(): array
    {
        $sql = "
            SELECT pp.id
            FROM participant_plan pp
            JOIN assessment a ON a.id = pp.assessment_id
            JOIN assessment_specification_implementation asi ON asi.implementation_assessment_id = a.id
            JOIN assess q ON q.assessment_id = a.id
        ";
        return array_map('intval', $this->schoolboxDb->executeQuery($sql)->fetchAll(PDO::FETCH_COLUMN));
    }


    /** @inheritDoc */
    protected function resetCounts(): void
    {
        parent::resetCounts();
        $this->countQuizSectionsCreated = 0;
        $this->countQuizSectionsDeleted = 0;
    }


    /** @inheritDoc */
    protected function printCounts(): void
    {
        parent::printCounts();

        $this->log->notice($this->countQuizSectionsCreated . " Schoolbox Quiz Impl Sections created");
        $this->log->notice($this->countQuizSectionsDeleted . " Schoolbox Quiz Impl Sections deleted");
    }


    /**
     * Returns all assess.externalId (aka quiz.externalId)
     *
     * @return int[]
     * @throws Database\Exception
     */
    private function getQuizCanvasIds(): array
    {
        $sql      = "
            SELECT q.external_id
            FROM assess q
            JOIN assessment a ON a.id = q.assessment_id
            WHERE q.external_id IS NOT NULL AND a.deleted_at IS NULL;
        ";
        $callback = static function (string $externalId): int {
            return (int)$externalId;
        };
        return array_map($callback, $this->schoolboxDb->executeQuery($sql)->fetchAll(PDO::FETCH_COLUMN));
    }
}
