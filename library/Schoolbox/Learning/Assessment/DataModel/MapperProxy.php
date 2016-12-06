<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment\DataModel;

use Schoolbox\Assessment\Rubric\Rubric;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Assessment\Component\SubmissionBox as DueWork;
use Schoolbox\Assessment\Component\SubmissionBoxMapper as DueWorkMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\Project\Mapper as ProjectMapper;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as Project;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkModel;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project as ProjectObject;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

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
    }

    /**
     * Finds an assessable by ID.
     *
     * This method does not load participants.
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
     * This method does not load participants.
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
     * This method loads participants, responses and feedback.
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
     * Find an assessment for the given quiz ID.
     *
     * This is intended to ease transition from Handlerland to the Aura
     * promised land - Handlerland quiz URLs will always have a quiz ID.
     * Eventually, this will be deprecated.
     *
     * This method loads participants, responses and feedback.
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
     * Currently, this does not load participants - I am not yet sure which
     * context this method will be used.
     *
     * @param int $assessmentId
     * @param array $options
     * @return IAssessable[]
     */
    public function findAllImplementationsBySpecificationId($assessmentId, $options = [])
    {
        // Get all assessables which have this assessment as a spec
        $params            = $this->convertOptionsToColumnList($options);
        $params['spec.id'] = $assessmentId;
        return $this->translateToDomainModelCollection($this->baseMapper->findByColumnList($params, $options));
    }

    /**
     * Find all assessables for this context.
     *
     * If $context is a Folder, this method does not retrieve assessables that
     * are within a course implementation that has been created on that folder.
     *
     * This method does not load participants.
     *
     * @param AssessmentContext $context
     * @param array             $options
     * @return Collection|IAssessable[]
     */
    public function findAllByContext(AssessmentContext $context, $options = [])
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
        return new Collection();
    }

    /**
     * Finds all assessables for a unit specification.
     *
     * This method does not load participants, because assessments in a unit spec
     * do not have participants.
     *
     * @param int   $unitSpecId
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllByUnitSpecificationId($unitSpecId, $options = [])
    {
        $params          = $this->convertOptionsToColumnList($options);
        $params['us.id'] = $unitSpecId;
        return $this->translateToDomainModelCollection($this->baseMapper->findByColumnList($params, $options));
    }

    /**
     * Finds all assessables for a unit implementation.
     *
     * This method does not currently load participants.
     *
     * @param int   $unitImplId
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllByUnitImplementationId($unitImplId, $options = [])
    {
        $params          = $this->convertOptionsToColumnList($options);
        $params['ui.id'] = $unitImplId;
        return $this->translateToDomainModelCollection($this->baseMapper->findByColumnList($params, $options));
    }

    /**
     * Finds all assessables for a unit implementation.
     *
     * This method loads participants, responses and feedback.
     *
     * @param int   $unitImplId
     * @param User  $student If provided, load only this student's participation
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllByUnitImplementationIdForCourseOutline($unitImplId, User $student = null, $options = [])
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
     * This method does not currently load participants.
     *
     * @param int   $folderId A folder or list of folders
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllByFolderId($folderId, $options = [])
    {
        return $this->translateToDomainModelCollection(
            $this->baseMapper->findAllForFolderId(
                $folderId,
                $this->convertOptionsToColumnList($options),
                $options
            )
        );
    }

    /**
     * Finds all assessables for a folder for review.
     *
     * @param int   $folderId A folder or list of folders
     * @param User  $student If provided, load only this student's participation
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllByFolderIdForReview($folderId, User $student = null, $options = [])
    {
        return $this->loadParticipantsIntoAssessmentCollection(
            $this->translateToDomainModelCollection(
                $this->baseMapper->findAllForFolderId(
                    $folderId,
                    $this->convertOptionsToColumnList($options),
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
     * This method does not currently load participants.
     *
     * @param int   $folderId
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllByFolderIdExclusively($folderId, $options = [])
    {
        $params         = $this->convertOptionsToColumnList($options);
        $params['f.id'] = $folderId;
        return $this->translateToDomainModelCollection($this->baseMapper->findByColumnList($params, $options));
    }

    /**
     * Finds all assessables that have been imported into a folder through a
     * course import.
     *
     * This method does not currently load participants.
     *
     * @deprecated Use `findAllForFolderId` to find assessables in a folder,
     *             along with any that are in a unit import on that folder.
     *
     * @param int   $folderId
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllByCourseImplementationFolderId($folderId, $options = [])
    {
        $params            = $this->convertOptionsToColumnList($options);
        // uicf.id = Unit Implementation Course Folder ID
        $params['uicf.id'] = $folderId;
        return $this->translateToDomainModelCollection($this->baseMapper->findByColumnList($params, $options));
    }

    /**
     * Finds all assessables that are part of the given project.
     *
     * This method does not load participants - see `$this->findAllByProjectIdForReview`
     * for a version which does.
     *
     * @param int   $projectId The assessment ID of the project (as opposed to the project's internal ID)
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllByProjectId($projectId, $options = [])
    {
        $params                     = $this->convertOptionsToColumnList($options);
        $params['pa.assessment_id'] = $projectId;
        return $this->translateToDomainModelCollection($this->baseMapper->findByColumnList($params, $options));
    }

    /**
     * Finds all assessables that are part of the given project, along with
     * all participation.
     *
     * @param int   $projectId The assessment ID of the project (as opposed to the project's internal ID)
     * @param User  $student If provided, load only this student's participation
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllByProjectIdForReview($projectId, User $student = null, $options = [])
    {
        $assessments = $this->findAllByProjectId($projectId, $options);
        return $this->loadParticipantsIntoAssessmentCollection($assessments, $student);
    }

    /**
     * Finds all assessables that this student is participating in.
     *
     * This method loads participation for $student only.
     *
     * @param User $student
     * @param Term $term    Restrict results to this term
     * @return Collection|IAssessable[]
     */
    public function findAllByStudent(User $student, Term $term)
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
        $extraParams = $this->convertOptionsToColumnList(['term' => $term,]);

        // Find assessments which this student is no longer in the class for,
        // but has created a response or feedback for.
        $extraParams['id'] = $this->baseMapper->findUniqueAssessmentIdsForStudent($student, $term);

        return $this->loadParticipantsIntoAssessmentCollection(
            $this->translateToDomainModelCollection(
                $this->baseMapper->findAllForFolderId($classFolderIds, $extraParams)
            ),
            $student
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
            // TODO figure out what to do, if anything, about rubric result sets
            // I think they should be removed, as a RRS is only ever used to set levels on a rubric.
            // Thereafter, it's ignored.
            $subObject->setRubricResultSet(0);

            DueWorkMapper::getInstance()->save($subObject);

            // Save the Rubric
            /** @var DueWorkModel $dueWork */
            $dueWork = $assessable;
            if($dueWork->getRubric()){
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
     */
    public function translateToDomainModelCollection(Collection $collection)
    {
        $newCollection = new Collection();
        foreach ($collection as $model) {
            if ($model instanceof Model) {
                try {
                    $newCollection->add($this->translateToDomainModel($model));
                } catch (\UnexpectedValueException $e) {
                    // Broken object from database - not much I can do but...
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
     * Helper function for converting a list of options into a column list.
     *
     * Currently, only converts "term" to a due date span.
     *
     * @param array $options
     * @return array
     */
    protected function convertOptionsToColumnList($options)
    {
        $columnList = [];
        if ($options['term'] instanceof Term) {
            $term = $options['term'];
            if ($term->getStart() instanceof \Alaress_Date_Format && !$term->getStart()->isNull()) {
                $columnList['due_date >='] = $term->getStart()->getDatabaseDate();
            }
            if ($term->getActualFinish() instanceof \Alaress_Date_Format && !$term->getActualFinish()->isNull()) {
                $columnList['due_date <='] = $term->getActualFinish()->getDatabaseDate();
            }
        }
        return $columnList;
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
     * @return Collection
     */
    public function loadParticipantsIntoAssessmentCollection(
        Collection $collection,
        User $user = null,
        $includeResponses = true,
        $includeFeedbacks = true
    ) {
        return $collection->map(function(IAssessable $assessable) use ($user, $includeResponses, $includeFeedbacks) {
            return $this->loadParticipantsIntoAssessment($assessable, $user, $includeResponses, $includeFeedbacks);
        });
    }
}