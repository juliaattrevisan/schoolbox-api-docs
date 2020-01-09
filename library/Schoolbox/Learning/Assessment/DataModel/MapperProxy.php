<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Database\Predicate\SqlFunc\Coalesce;
use Alaress_Date_Format;
use DomainException;
use InvalidArgumentException;
use LogicException;
use PDOException;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\Project\Mapper as ProjectMapper;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as Project;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkObject;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Project as ProjectObject;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use TypeError;
use UnderflowException;
use UnexpectedValueException;

/**
 * Proxy mapper for IAssessable objects.
 *
 * It simulates some parts of a Mapper's interface, and acts as a proxy for mappers.
 */
class MapperProxy
{
    /**
     * The mapper for the base assessment table.
     *
     * @var Mapper
     */
    protected $baseMapper;

    /**
     * The translator between domain and data objects.
     *
     * @var Translator
     */
    protected $translator;

    /**
     * Data source for participants in assessments.
     *
     * @var ParticipantDataSource
     */
    protected $participantDataSource;

    /**
     * Class name of the model to associate with this mapper.
     *
     * @var string
     */
    private $model;

    /**
     * A notifier for changes to assessments.
     *
     * @var NotifierProxy
     */
    private $notifier;

    /**
     * Constructor
     *
     * @param Mapper                $baseMapper
     * @param Translator            $translator
     * @param ParticipantDataSource $participantDataSource
     */
    public function __construct(
        Mapper $baseMapper,
        Translator $translator,
        ParticipantDataSource $participantDataSource
    ) {
        $this->baseMapper            = $baseMapper;
        $this->translator            = $translator;
        $this->participantDataSource = $participantDataSource;
        $this->model                 = IAssessable::class;
    }


    /**
     * Finds an assessable by ID.
     *
     * This method does not load participants, but does load the rubric grid.
     *
     * @param int $id
     * @return IAssessable
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws MapperException\Deleted if the assessment was deleted
     * @throws UnexpectedValueException If unable to derive project participant collections
     */
    public function findById($id)
    {
        return $this->findOneByColumn('id', $id, false);
    }

    /**
     * Find by ID for modification.
     *
     * This method does not load participants, but does load the rubric grid.
     *
     * @param int $id
     * @return IAssessable
     * @throws MapperException\Deleted if the assessment was deleted
     * @throws MapperException         if the assessment doesn't exist at all
     * @throws DomainException If user has interaction but not participant record
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws UnexpectedValueException If unable to derive project participant collections
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     */
    public function findByIdForModify($id)
    {
        return $this->findById($id);
    }


    /**
     * Find by ID for review, either of a single student or all in the class.
     *
     * This method loads participants, responses and feedback. It does not load the rubric grid.
     *
     * @param int       $id
     * @param null|User $student If provided, load only this student's participation
     * @return IAssessable
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws MapperException\Deleted if the assessment was deleted
     * @throws UnexpectedValueException If unable to derive project participant collections
     */
    public function findByIdForReview($id, User $student = null)
    {
        return $this->findOneByColumn('id', $id, true, $student);
    }


    /**
     * Finds all assessments that match the list of IDs.
     *
     * This method does not load participants.
     *
     * @param int[] $ids
     * @param array $options
     * @return GenericCollection|IAssessable[]
     * @throws DomainException If MarkType is invalid for the assessment
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     */
    public function findAllByIds($ids, array $options = [])
    {
        if (!\count($ids)) {
            return $this->createCollection();
        }
        return $this->translateToDomainModelCollection($this->baseMapper->findByColumn('id', $ids, $options));
    }

    /**
     * Find an assessment for the given quiz ID.
     *
     * This is intended to ease transition from Handlerland to the Aura
     * promised land - Handlerland quiz URLs will always have a quiz ID.
     * Eventually, this will be deprecated.
     *
     * This method loads participants, responses and feedback.
     * It does not load the rubric grid, because quizzes don't have rubrics.
     *
     * @param int  $quizId
     * @param User $student If provided, load only this student's participation
     * @return IAssessable|\Schoolbox\Learning\Assessment\Quiz\Model
     * @throws MapperException\Deleted if the quiz is deleted
     * @throws DomainException If user has interaction but not participant record
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws UnexpectedValueException If unable to derive project participant collections
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     */
    public function findByQuizId($quizId, User $student = null)
    {
        return $this->findOneByColumn('q.assess_id', $quizId, true, $student);
    }

    /**
     * Find an assessment for the given due work ID.
     *
     * This is intended to ease transition from Handlerland to the Aura
     * promised land - Handlerland due work URLs will always have a due work ID.
     * Eventually, this will be deprecated.
     *
     * This method loads participants, responses and feedback.
     * It does not load the rubric grid, because rubrics shouldn't be used
     * in any context where this method is used.
     *
     * @param int  $dueWorkId
     * @param User $student If provided, load only this student's participation
     * @return IAssessable
     * @throws MapperException\Deleted if the due-work is deleted
     * @throws DomainException If user has interaction but not participant record
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws UnexpectedValueException If unable to derive project participant collections
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     */
    public function findByDueWorkId($dueWorkId, User $student = null)
    {
        return $this->findOneByColumn('d.id', $dueWorkId, true, $student);
    }


    /**
     * Find an assessment for the given task ID.
     *
     * This is intended to ease transition from Handlerland to the Aura
     * promised land - Handlerland task URLs will always have a task ID.
     * Eventually, this will be deprecated.
     *
     * This method loads participants, responses and feedback.
     * It does not load the rubric grid, because tasks don't have rubrics.
     *
     * @param int  $taskId
     * @param User $student If provided, load only this student's participation
     * @return IAssessable
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws MapperException\Deleted if the task is deleted
     * @throws UnexpectedValueException If unable to derive project participant collections
     */
    public function findByTaskId($taskId, User $student = null)
    {
        return $this->findOneByColumn('t.id', $taskId, true, $student);
    }


    /**
     * Finds the specification assessment for a implementation assessment.
     *
     * This method does not load participants, because assessments in a unit spec
     * do not have participants.
     * It does load the rubric grid.
     *
     * @param int $assessmentId
     * @return IAssessable
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws MapperException\Deleted if the assessment was deleted
     * @throws UnexpectedValueException If unable to derive project participant collections
     */
    public function findSpecificationByImplementationId($assessmentId)
    {
        // Find the assessable which this assessment implements
        // TODO figure out how to do this in one query
        $model = $this->baseMapper->findById($assessmentId);
        return $this->findById($model->getSpecificationAssessment()->getId());
    }


    /**
     * Finds the specification assessment for a implementation assessment.
     *
     * This method loads participants, responses and feedback.
     *
     * @param int   $assessmentId
     * @param array $options
     * @return IAssessable[]
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws UnexpectedValueException If unable to merge student collections
     */
    public function findAllImplementationsBySpecificationId($assessmentId, array $options = [])
    {
        try {
            // Get all assessables which have this assessment as a spec
            $options['spec_assessment_id'] = $assessmentId;
            $builder                       = $this->convertOptionsToPredicateBuilder($options, false);
            return $this->loadParticipantsIntoAssessmentCollection(
                $this->translateToDomainModelCollection(
                    $this->baseMapper->findByPredicates($builder, $options)
                )
            );
        } catch (UnderflowException $e) {
            return $this->createCollection();
        }
    }


    /**
     * Find all assessables for this context.
     *
     * If $context is a Folder, this method does not retrieve assessables that
     * are within a course implementation that has been created on that folder.
     *
     * This method does not load participants or the rubric grid.
     *
     * @param AssessmentContext $context
     * @param array             $options
     * @return Collection|IAssessable[]
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws LogicException If cyclic graph detected during translation
     */
    public function findAllByContext(AssessmentContext $context, array $options = [])
    {
        if ($context instanceof Folder) {
            return $this->findAllByFolderIdExclusively($context->getId(), $options);
        }
        if ($context instanceof UnitSpecification) {
            return $this->findAllByUnitSpecificationId($context->getId(), $options);
        }
        if ($context instanceof UnitImplementation) {
            return $this->findAllByUnitImplementationIds([$context->getId()], $options);
        }
        if ($context instanceof ProjectObject) {
            return $this->findAllByProjectId($context->getId(), $options);
        }
        return $this->createCollection();
    }


    /**
     * Finds all assessables for a unit specification.
     *
     * This method does not load participants, because assessments in a unit spec
     * do not have participants.
     * Currently, this does not load the rubric grid - a variant which does load
     * the grid may need to be implemented.
     *
     * @param int   $unitSpecId
     * @param array $options
     * @return Collection|IAssessable[]
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws LogicException If cyclic graph detected during translation
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     */
    public function findAllByUnitSpecificationId($unitSpecId, array $options = [])
    {
        try {
            $builder = $this->convertOptionsToPredicateBuilder($options, false)
                            ->equals('us.id', $unitSpecId);
            return $this->translateToDomainModelCollection($this->baseMapper->findByPredicates($builder, $options));
        } catch (UnderflowException $e) {
            return $this->createCollection();
        }
    }


    /**
     * Finds all assessables for a unit implementation.
     *
     * This method does not currently load participants or the rubric grid.
     *
     * @param int[] $unitImplIds
     * @param array $options
     * @return Collection|IAssessable[]
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws LogicException If cyclic graph detected during translation
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     */
    public function findAllByUnitImplementationIds(array $unitImplIds, array $options = [])
    {
        try {
            $builder = $this->convertOptionsToPredicateBuilder($options, false)
                            ->in('ui.id', $unitImplIds);
            return $this->translateToDomainModelCollection($this->baseMapper->findByPredicates($builder, $options));
        } catch (UnderflowException $e) {
            return $this->createCollection();
        }
    }


    /**
     * Finds all assessables for a unit implementation.
     *
     * This method loads participants, responses and feedback.
     *
     * @param int[]     $unitImplIds
     * @param null|User $student If provided, load only this student's participation
     * @param array     $options
     * @return Collection|IAssessable[]
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws DomainException If user has interaction but not participant record
     * @throws UnexpectedValueException If unable to merge student collections
     * @throws LogicException If cyclic graph detected during translation
     */
    public function findAllByUnitImplementationIdsForCourseOutline(array $unitImplIds, User $student = null, array $options = [])
    {
        return $this->loadParticipantsIntoAssessmentCollection(
            $this->findAllByUnitImplementationIds($unitImplIds, $options),
            $student
        );
    }


    /**
     * Finds all assessables for a folder for review.
     *
     * This method loads participants, responses, feedback and rubric grids.
     *
     * @param int|int[] $folderId A folder or list of folders
     * @param User      $student  If provided, load only this student's participation
     * @param array     $options
     * @return Collection|IAssessable[]
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws DomainException If user has interaction but not participant record
     * @throws UnexpectedValueException If unable to merge student collections
     * @throws LogicException If cyclic graph detected during translation
     */
    public function findAllByFolderIdForReview($folderId, User $student = null, array $options = [])
    {
        if ($student instanceof User) {
            $assessments = $this->baseMapper->findForFolderAndParticipant(
                [$folderId],
                $student,
                $this->convertOptionsToPredicateBuilder($options, true),
                [],
                $options
            );
        } else {
            $assessments = $this->baseMapper->findAllForFolderId(
                $folderId,
                $this->convertOptionsToPredicateBuilder($options, false),
                [],
                $options
            );
        }

        return $this->loadParticipantsIntoAssessmentCollection($this->translateToDomainModelCollection($assessments), $student);
    }

    /**
     * Finds all assessables for a folder, not including course implementation
     * assessables.
     *
     * This method does not currently load participants or the rubric grid.
     *
     * @param int   $folderId
     * @param array $options
     * @return Collection|IAssessable[]
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws LogicException If cyclic graph detected during translation
     */
    public function findAllByFolderIdExclusively(int $folderId, array $options = [])
    {
        try {
            $builder = $this->convertOptionsToPredicateBuilder($options, false)
                            ->equals('f.id', $folderId);
            return $this->translateToDomainModelCollection($this->baseMapper->findByPredicates($builder, $options));
        } catch (UnderflowException $e) {
            return $this->createCollection();
        }
    }

    /**
     * Finds all assessables that have been imported into a folder through a
     * course import.
     *
     * This method does not currently load participants or the rubric grid.
     *
     * @deprecated Use `findAllForFolderId` to find assessables in a folder,
     *             along with any that are in a unit import on that folder.
     *
     * @param int   $folderId
     * @param array $options
     * @return Collection|IAssessable[]
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws LogicException If cyclic graph detected during translation
     */
    public function findAllByCourseImplementationFolderId($folderId, array $options = [])
    {
        try {
            // uicf.id = Unit Implementation Course Folder ID
            $builder = $this->convertOptionsToPredicateBuilder($options, false)
                            ->equals('uicf.id', $folderId);
            return $this->translateToDomainModelCollection($this->baseMapper->findByPredicates($builder, $options));
        } catch (UnderflowException $e) {
            return $this->createCollection();
        }
    }

    /**
     * Finds all assessables that are part of the given project.
     *
     * This method does not load participants or the rubric grid - see
     * `$this->findAllByProjectIdForReview` for a version which does.
     *
     * @param int   $projectId The assessment ID of the project (as opposed to the project's internal ID)
     * @param array $options
     * @return Collection|IAssessable[]
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws LogicException If cyclic graph detected during translation
     */
    public function findAllByProjectId($projectId, array $options = [])
    {
        try {
            $builder = $this->convertOptionsToPredicateBuilder($options, false)
                            ->equals('pa.assessment_id', $projectId);
            return $this->translateToDomainModelCollection($this->baseMapper->findByPredicates($builder, $options));
        } catch (UnderflowException $e) {
            return $this->createCollection();
        }
    }


    /**
     * Finds all assessables that are part of the given project, along with
     * all participation and rubric grids.
     *
     * @param int   $projectId The assessment ID of the project (as opposed to the project's internal ID)
     * @param User  $student   If provided, load only this student's participation
     * @param array $options
     * @return Collection|IAssessable[]
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws DomainException If user has interaction but not participant record
     * @throws UnexpectedValueException If unable to merge student collections
     * @throws LogicException If cyclic graph detected during translation
     */
    public function findAllByProjectIdForReview($projectId, User $student = null, array $options = [])
    {
        $assessments = $this->findAllByProjectId($projectId, $options);
        return $this->loadParticipantsIntoAssessmentCollection($assessments, $student);
    }


    /**
     * Find every assessment due between one of these dates.
     *
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     * @param array|null          $ids If provided, restrict search to these IDs
     * @return GenericCollection|IAssessable[]
     */
    public function findAllBetweenDates(
        Alaress_Date_Format $from,
        Alaress_Date_Format $to,
        array $ids = null
    ): GenericCollection {
        $options['due_after']  = $from;
        $options['due_before'] = $to;
        $builder               = $this->convertOptionsToPredicateBuilder($options, false);

        if (isset($ids)) {
            if (empty($ids)) {
                // A list of IDs was passed but it's empty. No matches are possible
                return $this->createCollection();
            }

            // otherwise, build predicates for the non-empty list of IDs
            $builder->in('id', $ids);
        }
        return $this->translateToDomainModelCollection($this->baseMapper->findByPredicates($builder, $options));
    }


    /**
     * Finds all assessables that this student is participating in.
     *
     * This method loads participation for $student only, and does not load the
     * rubric grid.
     *
     * Even when interested in a specific student, we may or may not be interested in filtering by the
     * assessment dates or the student's custom plan dates. It depends on the purpose:
     * - Aggregation across a time period (eg grades for a term): use assessment dates, regardless of custom plan dates
     * - Display of assessments (eg a list or calendar table): use custom plan dates if possible
     *
     * @param User  $student
     * @param bool  $usePlanDates Filter by custom plan dates instead of assessment dates?
     * @param Term  $term         Restrict results to this term
     * @param array $options
     * @return Collection|IAssessable[]
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws LogicException If cyclic graph detected during translation
     * @throws UnexpectedValueException If unable to merge project participant collections
     */
    public function findAllByStudent(User $student, bool $usePlanDates, Term $term, array $options = [])
    {
        return $this->findAllByStudentBetweenDates($student, $usePlanDates, $term->getStart(), $term->getActualFinish(), $options);
    }


    /**
     * Finds all assessables that this student is participating in, between the given dates.
     *
     * @param User                $student
     * @param bool                $usePlanDates Should we filter by assessment dates or plan dates?
     * @param Alaress_Date_Format $from         If provided, fetch only assessables due on or after this date
     * @param Alaress_Date_Format $to           If provided, fetch only assessables due on or before this date
     * @param array               $options      Any other query options
     * @return Collection|IAssessable[]
     * @throws DefinitionException If specified join mapper unable to not registered
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws DomainException If user has interaction but not participant record
     * @throws UnexpectedValueException If unable to merge project participant collections
     * @throws LogicException If cyclic graph detected during translation
     */
    public function findAllByStudentBetweenDates(
        User $student,
        bool $usePlanDates,
        Alaress_Date_Format $from = null,
        Alaress_Date_Format $to = null,
        array $options = []
    ) {
        /** @var FolderMapper $folderMapper */
        $folderMapper = $this->baseMapper->getJoinMapper('assessment_folder');

        // Find this user's folders from folder mapper
        $classFolderIds = $folderMapper->findForUser(
            $student,
            // Only actual students have classes
            $student->getRole()->isStudent(),
            UserAccess::ACCESS_READ,
            true
        )->getPropertyValues('id');

        // Build a list of parameters to pass to findAllForFolderId
        $options['due_after']  = $from;
        $options['due_before'] = $to;
        $builder               = $this->convertOptionsToPredicateBuilder($options, $usePlanDates);

        // Find assessments which this student is no longer in the class for,
        // but has created a response or feedback for.
        $uniqueAssessmentIds = $this->baseMapper->findAssessmentsWithInteractions($student, $usePlanDates, $from, $to);

        if ($usePlanDates) {
            $assessments = $this->baseMapper->findForFolderAndParticipant(
                $classFolderIds,
                $student,
                $builder,
                $uniqueAssessmentIds,
                $options
            );
        } else {
            $assessments = $this->baseMapper->findAllForFolderId(
                $classFolderIds,
                $builder,
                $uniqueAssessmentIds,
                $options
            );
        }

        return $this->loadParticipantsIntoAssessmentCollection($this->translateToDomainModelCollection($assessments), $student);
    }


    /**
     * Finds all assessables for this student that overlap a window.
     *
     * This is subtly different to `findAllByStudentBetweenDates` - that method
     * only fetches assessments that end between `$from` and `$to`, this method
     * fetches all assessments whose publish to end date span overlaps the window.
     *
     * @param User                $student
     * @param bool                $usePlanDates Filter by plan dates instead of assessment dates
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     * @param array               $options      Any other query options
     * @return Collection|IAssessable[]
     * @throws DefinitionException If specified join mapper not registered
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws DomainException If user has interaction but not participant record
     * @throws UnexpectedValueException If unable to merge student collections
     * @throws LogicException If cyclic graph detected during translation
     */
    public function findAllByStudentWithinWindow(
        User $student,
        bool $usePlanDates,
        Alaress_Date_Format $from,
        Alaress_Date_Format $to,
        array $options = []
    ) {
        /** @var FolderMapper $folderMapper */
        $folderMapper = $this->baseMapper->getJoinMapper('assessment_folder');

        // Find this user's folders from folder mapper
        $classFolderIds = $folderMapper->findForUser(
            $student,
            // Only actual students have classes
            $student->getRole()->isStudent(),
            UserAccess::ACCESS_READ,
            true
        )->getPropertyValues('id');

        // Build a list of parameters to pass to findAllForFolderId
        $options['window_open']  = $from;
        $options['window_close'] = $to;
        $builder                 = $this->convertOptionsToPredicateBuilder($options, $usePlanDates);

        // Find assessments which this student is no longer in the class for,
        // but has created a response or feedback for.
        $uniqueAssessmentIds = $this->baseMapper->findAssessmentsWithInteractions($student, $usePlanDates, $from, $to);

        if ($usePlanDates) {
            $assessments = $this->baseMapper->findForFolderAndParticipant(
                $classFolderIds,
                $student,
                $builder,
                $uniqueAssessmentIds
            );
        } else {
            $assessments = $this->baseMapper->findAllForFolderId(
                $classFolderIds,
                $builder,
                $uniqueAssessmentIds
            );
        }

        return $this->loadParticipantsIntoAssessmentCollection($this->translateToDomainModelCollection($assessments), $student);
    }


    /**
     * Finds all assessables for a staff member.
     *
     * This method does not loads participation.
     *
     * @param User $staff
     * @param Term $term Restrict results to this term
     * @return Collection|IAssessable[]
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws DefinitionException If specified join mapper cannot be found
     * @throws LogicException If cyclic graph detected during translation
     */
    public function findAllByStaff(User $staff, Term $term)
    {
        /** @var FolderMapper $folderMapper */
        $folderMapper = $this->baseMapper->getJoinMapper('assessment_folder');

        // Find this user's folders from folder mapper
        $classFolderIds = $folderMapper->findForUser(
            $staff,
            true,
            UserAccess::ACCESS_WRITE,
            false
        )->getColumnValues('id');

        // Build a list of parameters to pass to findAllForFolderId
        $predicate = $this->convertOptionsToPredicateBuilder(['term' => $term,], false);

        return $this->translateToDomainModelCollection(
            $this->baseMapper->findAllForFolderId($classFolderIds, $predicate)
        );
    }


    /**
     * Saves an assessable
     *
     * @param IAssessable $assessable
     * @throws MapperException If no instance of model is found
     * @throws MapperException\Deleted If the model was found but has been deleted
     * @throws UnexpectedValueException If a triggered callback failed, and caller should know about it
     * @throws PDOException If unable to execute SQL
     * @throws LogicException If cyclic graph detected during translation
     */
    public function save(IAssessable $assessable)
    {
        // Save assessment
        $dataModel = $this->translateToDataModel($assessable);
        // Folder context translation:
        // Look up the data model's assessment object ID, and translate to a project ID
        $context = $dataModel->getAssessmentContext();
        if ($context instanceof Project) {
            $project = ProjectMapper::getInstance()->findOneByColumn('assessment_id', $context->getAssessmentModel()->getId());
            $context->setId($project->getId());
        }

        /* TODO Remove this hack in SA-6049
         * If we are saving a new assessment, we need to save the assessment, then the sub-object (eg assess/box/task/project)
         * so that the sub-object has the assessment.id available.
         * BUT!! If an update, we must save the sub-object then the assessment. This is to work-around known issue SA-6049.
         * The issue is that the updated vs. previous diff during Mapper::save() causes sub-objects to be fetched from cache,
         * which will mutate your assessment model's composition, meaning you cannot persist joined object identities.
         */
        $isCreate = !$dataModel->getId();
        if ($isCreate) {
            $this->baseMapper->save($dataModel);
        }
        // Set fields on the domain model object
        $assessable
            ->setId($dataModel->getId())
            // Create/update/delete stamps aren't updated in the data model,
            // therefore it's useless to set them here.
//            ->setCreatedAt($dataModel->getCreatedAt())
//            ->setUpdatedAt($dataModel->getUpdatedAt())
//            ->setDeletedAt($dataModel->getDeletedAt())
        ;
        // Save sub-objects
        $subObject = $dataModel->getSubObject();
        $subObject->setId($this->getSubObjectPrimaryKeyValue($subObject));

        if ($subObject instanceof Task) {
            TaskMapper::getInstance()->save($subObject);

        } elseif ($subObject instanceof SubmissionBox) {
            // Legacy DueWork SitePage Controllers require that we preserve this field
            $subObject->setCategory($assessable->getBaseContext()->getFolder());
            $boxMapper = SubmissionBoxMapper::getInstance();

            /** @var DueWorkObject $dueWork */
            $dueWork = $assessable;
            if ($dueWork->getUseRubric()) {
                // Rubric involved? Save the due work, and then persist rubric
                $rubricMapper = RubricDataSource::getInstance();

                // Need to compare the previously existing grid with the newly
                // saved one, for invalidation purposes
                $originalGrid = null;
                if (!$isCreate) {
                    $originalGrid = $rubricMapper->retrieveGrid($assessable, false);
                }

                $boxMapper->save($subObject);

                if ($dueWork->getRubricGrid()) {
                    $rubricMapper->persistGrid($dueWork->getRubricGrid(), $originalGrid);
                }
            } else {
                // No rubric? Just save the due work
                $boxMapper->save($subObject);
            }

            $boxMapper->saveFiles($subObject);

        } elseif ($subObject instanceof Quiz) {
            // Legacy Quiz SitePage Controllers require that we preserve this field
            $subObject->setCategory($assessable->getBaseContext()->getFolder());
            QuizMapper::getInstance()->save($subObject);

        } elseif ($subObject instanceof Project) {
            ProjectMapper::getInstance()->save($subObject);
            // Save sub-projects
            if ($assessable instanceof ProjectObject) {
                foreach ($assessable->getAssessables() as $projectAssessable) {
                    // Disallow save of projects within projects
                    if ($projectAssessable instanceof ProjectObject) {
                        throw new \UnexpectedValueException('Projects may not contain projects');
                    }
                    $this->save($projectAssessable);
                }
            }
        }
        // TODO Remove this hack in SA-6049 (see description above)
        if (!$isCreate) {
            $this->baseMapper->save($dataModel);
        }

        // TODO If at all possible, trigger the assessment notifier here so that it contains all assessment information
        // Will need to move participant persistence here before that can happen though.

        // Load the dynamic participation record if this is new.
        if ($isCreate) {
            $this->participantDataSource->loadParticipationIntoAssessment($assessable);
        }
    }

    /**
     * Optimized save method for assessable order.
     *
     * @param Collection $assessables A list of assessables to resequence
     */
    public function sequenceAssessments(Collection $assessables)
    {
        $this->baseMapper->sequenceAssessments($assessables->getColumnValues('id'));
    }

    /**
     * Helper method for retrieving an assessable data model's primary key value
     * (as distinct from its assessment ID).
     *
     * The primary key needs to be set on a sub-object when saving it.
     *
     * @param IAssessableDataModel $subObject
     * @return int
     */
    protected function getSubObjectPrimaryKeyValue(IAssessableDataModel $subObject)
    {
        $assessmentId = $subObject->getAssessmentModel()->getId();
        if ($assessmentId) {
            if ($subObject instanceof Task) {
                $mapper = TaskMapper::getInstance();

            } elseif ($subObject instanceof SubmissionBox) {
                $mapper = SubmissionBoxMapper::getInstance();

            } elseif ($subObject instanceof Quiz) {
                $mapper = QuizMapper::getInstance();

            } elseif ($subObject instanceof Project) {
                $mapper = ProjectMapper::getInstance();
            } else {
                return null;
            }

            $ids = $mapper->findIdsByColumn('assessment_id', $assessmentId);
            return isset($ids[0]) ? $ids[0] : null;
        }
        return null;
    }


    /**
     * Deletes an assessable
     *
     * @param IAssessable $assessable
     * @throws UnexpectedValueException If assessment does not have a context
     * @throws LogicException If cyclic graph detected during translation
     * @throws MapperException
     */
    public function delete(IAssessable $assessable)
    {
        // Delete assessment
        $dataModel = $this->translateToDataModel($assessable);
        if ($dataModel->getSubObject() instanceof SubmissionBox) {
            // The assessment model is a component of the submission box.
            // The composite object is responsible for deleting its components
            // TODO - roll out this methodology to other assessment types
            SubmissionBoxMapper::getInstance()->delete($dataModel->getSubObject());
        } else {
            $this->baseMapper->delete($dataModel);
        }
        $assessable->setDeletedAt($dataModel->getDeletedAt());
        // I don't think I need to delete sub-objects.
        // The assessable itself is all that should need deletion
    }

    /**
     * Translates the data model to a domain object.
     *
     * @param Model $model
     * @return IAssessable
     * @throws UnexpectedValueException if $dataModel wasn't able to be translated
     * @throws DomainException If MarkType is invalid for the assessment
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     */
    public function translateToDomainModel(Model $model)
    {
        // Turn the data model inside out
        // TODO try to remove this, and update translator to return Model objects directly
        $subModel = $model->getSubObject();
        $subModel->setAssessmentModel($model);

        return $this->translator->translateToDomainModel($subModel);
    }


    /**
     * Translates a collection of data models to a domain object.
     *
     * Any broken data models just get skipped.
     *
     * @param Collection|Model[] $dataModels
     * @return Collection|IAssessable[]
     * @throws DomainException If MarkType is invalid for the assessment
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected during translation
     * @throws UnexpectedValueException If $dataModel wasn't able to be translated
     */
    public function translateToDomainModelCollection(Collection $dataModels)
    {
        $domainObjects = $this->createCollection();
        foreach ($dataModels as $idx => $dataModel) {
            if ($dataModel instanceof Model) {
                $domainObjects[$idx] = $this->translateToDomainModel($dataModel);
            }
        }
        return $domainObjects;
    }

    /**
     * Translates the domain object to a data model.
     *
     * @param IAssessable $assessable
     * @return Model
     * @throws UnexpectedValueException If assessment does not have a context
     * @throws LogicException If cyclic graph detected
     */
    public function translateToDataModel(IAssessable $assessable)
    {
        $dataModel = $this->translator->translateToDataModel($assessable);
        // Turn the data model inside out
        // TODO try to remove this, and update translator to accept Model objects directly
        $model = $dataModel->getAssessmentModel();
        $model->setSubObject($dataModel);
        return $model;
    }

    /**
     * Find a single assessment by a column and value.
     *
     * @param string    $column
     * @param mixed     $value
     * @param bool      $loadParticipants Should we load participations?
     * @param null|User $student          If specified, only load this user's interactions
     * @return IAssessable
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws MapperException\Deleted if the assessment was deleted
     * @throws UnexpectedValueException If unable to derive project participant collections
     */
    public function findOneByColumn(string $column, $value, bool $loadParticipants = false, User $student = null): IAssessable
    {
        try {
            // Load model and its participants
            $originalModel = $this->baseMapper->findOneByColumnList([$column => $value]);

            $assessment = $this->translateToDomainModel($originalModel);
            if ($loadParticipants) {
                $assessment = $this->participantDataSource->loadParticipationIntoAssessment($assessment, $student);
            }
            return $assessment;
        } // This can be thrown. The @method annotation masks exceptions thrown from base implementation.
            /** @noinspection PhpRedundantCatchClauseInspection */
        catch (MapperException\Deleted $e) {
            // Populate participants for the deleted assessment, and rethrow
            $assessment = $this->translateToDomainModel($e->getDeletedObject());
            if ($loadParticipants) {
                $assessment = $this->participantDataSource->loadParticipationIntoAssessment($assessment, $student);
            }
            $e->setDeletedObject($assessment);
            throw $e;
        }
    }


    /**
     * Helper function for converting a list of options into a predicate builder.
     *
     * Currently:
     * * converts "term" to a due date span
     * * converts "due_before" and "due_after" to due date filters
     * * converts "close_before" and "closed_after" to close date filters
     * * converts "type" to a filter which matches only that assessment type
     *   (see AssessmentType::TYPE_* constants)
     * * converts "window_open" and "window_close" to a window inside which
     *   to find due assessments
     *
     * @param array $options
     * @param bool  $usePlanDates Filter by plan dates instead of assessment dates
     * @return Builder
     * @throws InvalidColumnException If a PredicateBuilder method is called
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     */
    protected function convertOptionsToPredicateBuilder(array $options, bool $usePlanDates): Builder
    {
        $builder = $this->baseMapper->getPredicateBuilder();
        // Term filter (overrides due before/after)
        if ($options['term'] instanceof Term) {
            $term                  = $options['term'];
            $options['due_after']  = $term->getStart();
            $options['due_before'] = $term->getActualFinish();
        }

        $aTable = $this->baseMapper->getTable();

        // Are we interested in the custom plan dates, or the assessment dates?
        if ($usePlanDates) {
            $publishDateColumn = new Coalesce(['participant_plan.publish_date', "$aTable.publish_date"]);
            $dueDateColumn     = new Coalesce(['participant_plan.due_date', "$aTable.due_date"]);
            $closeDateColumn   = new Coalesce(['participant_plan.close_date', "$aTable.close_date"]);
        } else {
            $publishDateColumn = "$aTable.publish_date";
            $dueDateColumn     = "$aTable.due_date";
            $closeDateColumn   = "$aTable.close_date";
        }


        // Due before / after filter (an ADF)
        if ($options['due_after'] instanceof \Alaress_Date_Format && !$options['due_after']->isNull()) {
            $builder->notLessThan($dueDateColumn, $options['due_after']->getDatabase());
        }
        if ($options['due_before'] instanceof \Alaress_Date_Format && !$options['due_before']->isNull()) {
            $builder->notGreaterThan($dueDateColumn, $options['due_before']->getDatabase());
        }

        // Close before / after filter (an ADF)
        if ($options['closed_after'] instanceof \Alaress_Date_Format && !$options['closed_after']->isNull()) {
            $builder->notLessThan($closeDateColumn, $options['closed_after']->getDatabase());
        }

        if ($options['close_before'] instanceof \Alaress_Date_Format && !$options['close_before']->isNull()) {
            $builder->notGreaterThan($closeDateColumn, $options['close_before']->getDatabase());
        }

        // Window before / after filter (an ADF)
        if ($options['window_open'] instanceof \Alaress_Date_Format
            && $options['window_close'] instanceof \Alaress_Date_Format
            && !$options['window_open']->isNull()
            && !$options['window_close']->isNull()
        ) {
            // NOT (assess.end < window.open OR assess.start >= window.close)
            $builder->notAny(
                $this->baseMapper->getPredicateBuilder()
                                 ->lessThan($dueDateColumn, $options['window_open'])
                                 ->notLessThan($publishDateColumn, $options['window_close'])
            );
        }

        // Assessment type filter
        switch ($options['type']) {
            case AssessmentType::TYPE_DUE_WORK:
                $builder->notNull('d.id');
                break;

            case AssessmentType::TYPE_TASK:
                $builder->notNull('t.id');
                break;

            case AssessmentType::TYPE_QUIZ:
                $builder->notNull('q.assess_id');
                break;

            case AssessmentType::TYPE_PROJECT:
                $builder->notNull('p.id');
                break;

            default:
                break;
        }

        // Work type filter
        if (isset($options['work_type'])) {
            $builder->equals('work_type_id', $options['work_type']);
        }

        // Assessment title filter?
        if (isset($options['title'])) {
            $builder->equals('title', $options['title']);
        }

        // Spec assessment filter?
        if (isset($options['spec_assessment_id'])) {
            $builder->equals('v_spec.specification_assessment_id', $options['spec_assessment_id']);
        }

        return $builder;
    }


    /**
     * Loads participants into a collection of assessables.
     *
     * @param Collection $collection
     * @param User       $user           If specified, only load this user's interactions
     * @param bool       $removeExcluded Set to true to exclude excluded non-member participants
     * @return Collection|IAssessable[]
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws DomainException If user has interaction but not participant record
     * @throws UnexpectedValueException If unable to merge student collections
     */
    public function loadParticipantsIntoAssessmentCollection(
        Collection $collection,
        User $user = null,
        bool $removeExcluded = false
    ) {
        return $this->participantDataSource->loadParticipationIntoAssessmentCollection(
            $collection,
            $user,
            $removeExcluded
        );
    }

    /**
     * Override base mapper to always return new collection object
     *
     * @param array $contents
     *
     * @return GenericCollection|IAssessable[]
     */
    public function createCollection($contents = [])
    {
        return new GenericCollection($this->getModel(), $contents);
    }

    /**
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Retrieves the notifier that the mapper proxy uses.
     *
     * Currently, notifications must still be manually triggered using this
     * notifier.
     *
     * @return NotifierProxy
     */
    public function getNotifier(): NotifierProxy
    {
        if (!$this->notifier instanceof NotifierProxy) {
            $this->notifier = new NotifierProxy(
                $this,
                $this->participantDataSource->getPlanMapper(),
                $this->participantDataSource->getParticipantMapper()
            );
        }
        return $this->notifier;
    }
}
