<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment\DataModel;

use Alaress\Database\Predicate\Builder;
use Alaress_Date_Format;
use Schoolbox\Assessment\Component\SubmissionBox as DueWork;
use Schoolbox\Assessment\Component\SubmissionBoxMapper as DueWorkMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\Project\Mapper as ProjectMapper;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as Project;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Project as ProjectObject;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use UnderflowException;

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
     * @throws MapperException\Deleted if the assessment was deleted
     * @throws MapperException         if the assessment doesn't exist at all
     */
    public function findById($id)
    {
        return $this->findSingle('id', $id);
    }

    /**
     * Find by ID for modification.
     *
     * This method does not load participants, but does load the rubric grid.
     *
     * @param int $id
     * @return IAssessable
     */
    public function findByIdForModify($id)
    {
        return $this->findById($id);
    }

    /**
     * Find by ID for review, either of a single student or all in the class.
     *
     * This method loads participants, responses, feedback and rubric grid.
     *
     * @param int  $id
     * @param User $student If provided, load only this student's participation
     * @return IAssessable
     */
    public function findByIdForReview($id, User $student = null)
    {
        return $this->findSingle('id', $id, true, $student);
    }

    /**
     * Finds all assessments that match the list of IDs.
     *
     * This method does not load participants.
     *
     * @param int[] $ids
     * @param array $options
     * @return GenericCollection|IAssessable[]
     */
    public function findAllByIds($ids, array $options = [])
    {
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
     * @return IAssessable
     * @throws MapperException\Deleted if the quiz is deleted
     */
    public function findByQuizId($quizId, User $student = null)
    {
        return $this->findSingle('q.assess_id', $quizId, true, $student);
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
     * @throws MapperException\Deleted if the quiz is deleted
     */
    public function findByDueWorkId($dueWorkId, User $student = null)
    {
        return $this->findSingle('d.id', $dueWorkId, true, $student);
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
     * @throws MapperException\Deleted if the quiz is deleted
     */
    public function findByTaskId($taskId, User $student = null)
    {
        return $this->findSingle('t.id', $taskId, true, $student);
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
     * @throws MapperException\Deleted if the assessment was deleted
     * @throws MapperException         if the assessment doesn't exist at all
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
     * @param int $assessmentId
     * @param array $options
     * @return IAssessable[]
     */
    public function findAllImplementationsBySpecificationId($assessmentId, array $options = [])
    {
        try {
            // Get all assessables which have this assessment as a spec
            $options['spec_assessment_id'] = $assessmentId;
            $builder = $this->convertOptionsToPredicateBuilder($options);
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
            return $this->findAllByUnitImplementationId($context->getId(), $options);
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
     */
    public function findAllByUnitSpecificationId($unitSpecId, array $options = [])
    {
        try {
            $builder = $this->convertOptionsToPredicateBuilder($options)
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
     * @param int|int[] $unitImplId
     * @param array     $options
     * @return Collection|IAssessable[]
     */
    public function findAllByUnitImplementationId($unitImplId, array $options = [])
    {
        try {
            $builder = $this->convertOptionsToPredicateBuilder($options)
                            ->equals('ui.id', $unitImplId);
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
     * @param int|int[] $unitImplId
     * @param User      $student If provided, load only this student's participation
     * @param array     $options
     * @return Collection|IAssessable[]
     */
    public function findAllByUnitImplementationIdForCourseOutline($unitImplId, User $student = null, array $options = [])
    {
        return $this->loadParticipantsIntoAssessmentCollection(
            $this->findAllByUnitImplementationId($unitImplId, $options),
            $student
        );
    }

    /**
     * Finds all assessables for a folder.
     *
     * This now defaults to finding assessables that are within a course
     * implementation of the given folder.
     * This is more likely to be the expected behavior of this method.
     *
     * This method does not currently load participants or the rubric grid.
     *
     * @param int|int[] $folderId A folder or list of folders
     * @param array     $options
     * @return Collection|IAssessable[]
     */
    public function findAllByFolderId($folderId, array $options = [])
    {
        return $this->translateToDomainModelCollection(
            $this->baseMapper->findAllForFolderId(
                $folderId,
                $this->convertOptionsToPredicateBuilder($options),
                [],
                $options
            )
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
     */
    public function findAllByFolderIdForReview($folderId, User $student = null, array $options = [])
    {
        return $this->loadParticipantsIntoAssessmentCollection(
            $this->translateToDomainModelCollection(
                $this->baseMapper->findAllForFolderId(
                    $folderId,
                    $this->convertOptionsToPredicateBuilder($options),
                    [],
                    $options
                )
            ),
            $student
        );
    }

    /**
     * Finds all assessables for a folder, not including course implementation
     * assessables.
     *
     * This method does not currently load participants or the rubric grid.
     *
     * @param int|int[] $folderId
     * @param array     $options
     * @return Collection|IAssessable[]
     */
    public function findAllByFolderIdExclusively($folderId, array $options = [])
    {
        try {
            $builder = $this->convertOptionsToPredicateBuilder($options)
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
     */
    public function findAllByCourseImplementationFolderId($folderId, array $options = [])
    {
        try {
            // uicf.id = Unit Implementation Course Folder ID
            $builder = $this->convertOptionsToPredicateBuilder($options)
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
     */
    public function findAllByProjectId($projectId, array $options = [])
    {
        try {
            $builder = $this->convertOptionsToPredicateBuilder($options)
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
     * @param User  $student If provided, load only this student's participation
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllByProjectIdForReview($projectId, User $student = null, array $options = [])
    {
        $assessments = $this->findAllByProjectId($projectId, $options);
        return $this->loadParticipantsIntoAssessmentCollection($assessments, $student);
    }

    /**
     * Finds all assessables that this student is participating in.
     *
     * This method loads participation for $student only, and does not load the
     * rubric grid.
     *
     * @param User  $student
     * @param Term  $term    Restrict results to this term
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllByStudent(User $student, Term $term, $options = [])
    {
        return $this->findAllByStudentBetweenDates($student, $term->getStart(), $term->getActualFinish(), $options);
    }

    /**
     * Finds all assessables that this student is participating in, between the given dates.
     *
     * @param User                $student
     * @param Alaress_Date_Format $from    If provided, fetch only assessables due on or after this date
     * @param Alaress_Date_Format $to      If provided, fetch only assessables due on or before this date
     * @param array               $options Any other query options
     * @return Collection|IAssessable[]
     */
    public function findAllByStudentBetweenDates(
        User $student,
        Alaress_Date_Format $from = null,
        Alaress_Date_Format $to = null,
        $options = []
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
        )->getColumnValues('id');

        // Build a list of parameters to pass to findAllForFolderId
        $options['due_after']  = $from;
        $options['due_before'] = $to;
        $builder = $this->convertOptionsToPredicateBuilder($options);

        // Find assessments which this student is no longer in the class for,
        // but has created a response or feedback for.
        $uniqueAssessmentIds = $this->baseMapper->findUniqueAssessmentIdsForStudentBetweenDates($student, $from, $to);

        return $this->loadParticipantsIntoAssessmentCollection(
            $this->translateToDomainModelCollection(
                $this->baseMapper->findAllForFolderId(
                    $classFolderIds,
                    $builder,
                    $uniqueAssessmentIds
                )
            ),
            $student
        );
    }

    /**
     * Finds all assessables for this student that overlap a window.
     *
     * This is subtly different to `findAllByStudentBetweenDates` - that method
     * only fetches assessments that end between `$from` and `$to`, this method
     * fetches all assessments whose publish to end date span overlaps the window.
     *
     * @param User                $student
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     * @return Collection|IAssessable[]
     */
    public function findAllByStudentWithinWindow(User $student, Alaress_Date_Format $from, Alaress_Date_Format $to)
    {
        /** @var FolderMapper $folderMapper */
        $folderMapper = $this->baseMapper->getJoinMapper('assessment_folder');

        // Find this user's folders from folder mapper
        $classFolderIds = $folderMapper->findForUser(
            $student,
            // Only actual students have classes
            $student->getRole()->isStudent(),
            UserAccess::ACCESS_READ,
            true
        )->getColumnValues('id');

        // Build a list of parameters to pass to findAllForFolderId
        $builder = $this->convertOptionsToPredicateBuilder(['window_open' => $from, 'window_close' => $to]);

        // Find assessments which this student is no longer in the class for,
        // but has created a response or feedback for.
        $uniqueAssessmentIds = $this->baseMapper->findUniqueAssessmentIdsForStudentBetweenDates($student, $from, $to);

        return $this->loadParticipantsIntoAssessmentCollection(
            $this->translateToDomainModelCollection(
                $this->baseMapper->findAllForFolderId(
                    $classFolderIds,
                    $builder,
                    $uniqueAssessmentIds
                )
            ),
            $student
        );
    }

    /**
     * Finds all assessables for a staff member.
     *
     * This method does not loads participation.
     *
     * @param User $staff
     * @param Term $term    Restrict results to this term
     * @return Collection|IAssessable[]
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
        $predicate = $this->convertOptionsToPredicateBuilder(['term' => $term,]);

        return $this->translateToDomainModelCollection(
            $this->baseMapper->findAllForFolderId($classFolderIds, $predicate)
        );
    }

    /**
     * Saves an assessable
     *
     * @param IAssessable $assessable
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
        $this->baseMapper->save($dataModel);
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

        } elseif ($subObject instanceof DueWork) {
            // Legacy DueWork SitePage Controllers require that we preserve this field
            $subObject->setCategory($assessable->getBaseContext()->getFolder());

            DueWorkMapper::getInstance()->save($subObject);

            // Save the Rubric
            /** @var DueWorkModel $dueWork */
            $dueWork = $assessable;
            if ($dueWork->getRubric() && $dueWork->getUseRubric()) {
                $dueWork->getRubric()->setSubmissionBox($subObject);
                DueWork\Rubric\RubricMapper::getInstance()->save($dueWork->getRubric(), $subObject);
            }

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

            } elseif ($subObject instanceof DueWork) {
                $mapper = DueWorkMapper::getInstance();

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
     */
    public function delete(IAssessable $assessable)
    {
        // Delete assessment
        $dataModel = $this->translateToDataModel($assessable);
        $this->baseMapper->delete($dataModel);
        $assessable->setDeletedAt($dataModel->getDeletedAt());
        // I don't think I need to delete sub-objects.
        // The assessable itself is all that should need deletion
    }

    /**
     * Translates the data model to a domain object.
     *
     * @param Model $model
     * @return IAssessable
     */
    public function translateToDomainModel(Model $model)
    {
        // Turn the data model inside out
        // TODO try to remove this, and update translator to return Model objects directly
        $subModel = $model->getSubObject();
        $subModel->setAssessmentModel($model);
        // Retrieve sub-projects


        $assessable = $this->translator->translateToDomainModel($subModel);
        if ($assessable instanceof ProjectObject) {
            $assessable->setAssessables($this->findAllByProjectId($assessable->getId()));
        }
        return $assessable;
    }

    /**
     * Translates a collection of data models to a domain object.
     *
     * Any broken data models just get skipped.
     *
     * @param Collection|Model[] $collection
     * @return Collection|IAssessable[]
     * @throws \UnexpectedValueException If assessment's data is broken in database
     */
    public function translateToDomainModelCollection(Collection $collection)
    {
        $newCollection = $this->createCollection();
        foreach ($collection as $model) {
            if ($model instanceof Model) {
                try {
                    $newCollection->add($this->translateToDomainModel($model));
                } catch (\UnexpectedValueException $e) {
                    // Broken object from database - not much I can do but...
                    if(function_exists('newrelic_notice_error')){
                        newrelic_notice_error(null, $e);
                    }
                    continue;
                }
            }
        }
        return $newCollection;
    }

    /**
     * Translates the domain object to a data model.
     *
     * @param IAssessable $assessable
     * @return Model
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
     * Helper function for finding a single assessment by a column and value.
     *
     * @param string $column
     * @param int    $value
     * @param bool   $loadParticipants
     * @param User   $student
     * @return IAssessable
     * @throws MapperException\Deleted if the assessment was deleted
     * @throws MapperException         if the assessment doesn't exist at all
     */
    protected function findSingle($column, $value, $loadParticipants = false, User $student = null)
    {
        try {
            // Load model and its participants
            $originalModel = $this->baseMapper->findOneByColumnList([$column => $value]);
            $assessment    = $this->translateToDomainModel($originalModel);
            if ($loadParticipants) {
                $this->participantDataSource->loadParticipationIntoAssessment($assessment, $student);
            }
            return $assessment;

        } catch (MapperException\Deleted $e) {
            // Populate participants for the deleted assessment, and rethrow
            $assessment = $this->translateToDomainModel($e->getDeletedObject());
            if ($loadParticipants) {
                $this->participantDataSource->loadParticipationIntoAssessment($assessment, $student);
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
     *
     * @param array $options
     * @return Builder
     * @throws \InvalidArgumentException
     */
    protected function convertOptionsToPredicateBuilder($options)
    {
        $builder = $this->baseMapper->getPredicateBuilder();
        // Term filter (overrides due before/after)
        if ($options['term'] instanceof Term) {
            $term = $options['term'];
            $options['due_after']  = $term->getStart();
            $options['due_before'] = $term->getActualFinish();
        }

        /* It is not a valid query to filter from/to both a due and close date.
         * Typically when considering spans of time we are interested in the due date (learning analytics, markbook etc)
         * Typically when considering points in time (usually now) we are interested in close date (eg current visible DueWork)
         */
        if(array_key_exists('due_before', $options) && array_key_exists('close_before', $options) ){
            throw new \InvalidArgumentException('Cannot filter to before both due and close dates.');
        }
        if(array_key_exists('due_after', $options) && array_key_exists('closed_after', $options) ){
            throw new \InvalidArgumentException('Cannot filter to after both due and close dates.');
        }
        // Due before / after filter (an ADF)
        if ($options['due_after'] instanceof \Alaress_Date_Format && !$options['due_after']->isNull()) {
            $builder->notLessThan('due_date', $options['due_after']->getDatabase());
        }
        if ($options['due_before'] instanceof \Alaress_Date_Format && !$options['due_before']->isNull()) {
            $builder->notGreaterThan('due_date', $options['due_before']->getDatabase());
        }

        // Close before / after filter (an ADF)
        if ($options['closed_after'] instanceof \Alaress_Date_Format && !$options['closed_after']->isNull()) {
            $builder->notLessThan('close_date', $options['closed_after']->getDatabase());
        }

        if ($options['close_before'] instanceof \Alaress_Date_Format && !$options['close_before']->isNull()) {
            $builder->notGreaterThan('close_date', $options['close_before']->getDatabase());
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
                    ->lessThan('due_date', $options['window_open'])
                    ->notLessThan('publish_date', $options['window_close'])
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
     * Loads participants into the given assessable.
     *
     * @param IAssessable $assessable
     * @param User        $user
     * @param bool        $includeResponses
     * @param bool        $includeFeedbacks
     * @return IAssessable
     */
    public function loadParticipantsIntoAssessment(
        IAssessable $assessable,
        User $user = null,
        $includeResponses = true,
        $includeFeedbacks = true
    ) {
        $this->participantDataSource->loadParticipationIntoAssessment(
            $assessable,
            $user,
            $includeResponses,
            $includeFeedbacks
        );
        return $assessable;
    }

    /**
     * Loads participants into a collection of assessables.
     *
     * @param Collection $collection
     * @param User       $user
     * @param bool       $includeResponses
     * @param bool       $includeFeedbacks
     * @return Collection|IAssessable[]
     */
    public function loadParticipantsIntoAssessmentCollection(
        Collection $collection,
        User $user = null,
        $includeResponses = true,
        $includeFeedbacks = true
    ) {
        $this->participantDataSource->loadParticipationIntoAssessmentCollection(
            $collection,
            $user,
            $includeResponses,
            $includeFeedbacks
        );
        return $collection;
    }

    /**
     * Override base mapper to always return new collection object
     *
     * @param array $contents
     *
     * @return GenericCollection|IAssessable[]
     */
    public function createCollection($contents = array())
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
}
