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
    protected $particpantDataSource;

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
     * Finds an assessable by ID
     *
     * @param int $id
     * @return IAssessable
     * @throws MapperException\Deleted if the assessment was deleted
     * @throws MapperException         if the assessment doesn't exist at all
     */
    public function findById($id)
    {
        try {
            $originalModel = $this->baseMapper->findById($id);
            return $this->translateToDomainModel($originalModel);
        } catch (MapperException\Deleted $e) {
            $e->setDeletedObject($this->translateToDomainModel($e->getDeletedObject()));
            throw $e;
        }
    }

    /**
     * Finds the specification assessment for a implementation assessment
     *
     * @param int $assessmentId
     * @return IAssessable
     * @throws MapperException\Deleted if the assessment was deleted
     * @throws MapperException         if the assessment doesn't exist at all
     */
    public function findSpecificationForImplementationId($assessmentId)
    {
        // Find the assessable which this assessment implements
        // TODO figure out how to do this in one query
        $model = $this->baseMapper->findById($assessmentId);
        return $this->findById($model->getSpecificationAssessment()->getId());
    }

    /**
     * Finds the specification assessment for a implementation assessment
     *
     * @param int $assessmentId
     * @param array $options
     * @return IAssessable[]
     */
    public function findAllImplementationsForSpecificationId($assessmentId, $options = [])
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
     * @param AssessmentContext $context
     * @param array             $options
     * @return Collection|IAssessable[]
     */
    public function findAllForContext(AssessmentContext $context, $options = [])
    {
        if ($context instanceof Folder) {
            return $this->findAllForFolderIdExclusively($context->getId(), $options);
        }
        if ($context instanceof UnitSpecification) {
            return $this->findAllForUnitSpecificationId($context->getId(), $options);
        }
        if ($context instanceof UnitImplementation) {
            return $this->findAllForUnitImplementationId($context->getId(), $options);
        }
        if ($context instanceof ProjectObject) {
            return $this->findAllForProjectId($context->getId(), $options);
        }
        return new Collection();
    }

    /**
     * Finds all assessables for a unit specification
     *
     * @param int   $unitSpecId
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllForUnitSpecificationId($unitSpecId, $options = [])
    {
        $params          = $this->convertOptionsToColumnList($options);
        $params['us.id'] = $unitSpecId;
        return $this->translateToDomainModelCollection($this->baseMapper->findByColumnList($params, $options));
    }

    /**
     * Finds all assessables for a unit implementation
     *
     * @param int   $unitImplId
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllForUnitImplementationId($unitImplId, $options = [])
    {
        $params          = $this->convertOptionsToColumnList($options);
        $params['ui.id'] = $unitImplId;
        return $this->translateToDomainModelCollection($this->baseMapper->findByColumnList($params, $options));
    }

    /**
     * Finds all assessables for a folder.
     *
     * This now defaults to finding assessables that are within a course
     * implementation of the given folder.
     * This is more likely to be the expected behavior of this method.
     *
     * @param int   $folderId A folder or list of folders
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllForFolderId($folderId, $options = [])
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
     * Finds all assessables for a folder, not including course implementation
     * assessables.
     *
     * @param int   $folderId
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllForFolderIdExclusively($folderId, $options = [])
    {
        $params         = $this->convertOptionsToColumnList($options);
        $params['f.id'] = $folderId;
        return $this->translateToDomainModelCollection($this->baseMapper->findByColumnList($params, $options));
    }

    /**
     * Finds all assessables that have been imported into a folder through a
     * course import.
     *
     * @param int   $folderId
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllInCourseImplementationForFolderId($folderId, $options = [])
    {
        $params            = $this->convertOptionsToColumnList($options);
        // uicf.id = Unit Implementation Course Folder ID
        $params['uicf.id'] = $folderId;
        return $this->translateToDomainModelCollection($this->baseMapper->findByColumnList($params, $options));
    }

    /**
     * Finds all assessables that are part of the given project
     *
     * @param int   $projectId The assessment ID of the project (as opposed to the project's internal ID)
     * @param array $options
     * @return Collection|IAssessable[]
     */
    public function findAllForProjectId($projectId, $options = [])
    {
        $params                     = $this->convertOptionsToColumnList($options);
        $params['pa.assessment_id'] = $projectId;
        return $this->translateToDomainModelCollection($this->baseMapper->findByColumnList($params, $options));
    }

    /**
     * Finds all assessables that this student is participating in.
     *
     * @param User $student
     * @param Term $term    Restrict results to this term
     * @return Collection|IAssessable[]
     */
    public function findAllForStudent(User $student, Term $term)
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

        return $this->translateToDomainModelCollection(
            $this->baseMapper->findAllForFolderId($classFolderIds, $extraParams)
        );
    }

    /**
     * Saves an assessable
     *
     * @param IAssessable $assessable
     * @param int         $sequence
     */
    public function save(IAssessable $assessable, $sequence)
    {
        // Save assessment
        $dataModel = $this->translateToDataModel($assessable, $sequence);
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
                $i = 0;
                foreach ($assessable->getAssessables() as $projectAssessable) {
                    // Disallow save of projects within projects
                    if ($projectAssessable instanceof ProjectObject) {
                        throw new \UnexpectedValueException('Projects may not contain projects');
                    }
                    $this->save($projectAssessable, $i++);
                }
            }
        }
    }

    /**
     * Deletes an assessable
     *
     * @param IAssessable $assessable
     */
    public function delete(IAssessable $assessable)
    {
        // Delete assessment
        $this->baseMapper->delete($this->translateToDataModel($assessable, 0));
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
            $assessable->setAssessables($this->findAllForProjectId($assessable->getId()));
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
     * @param int         $sequence
     * @return Model
     */
    public function translateToDataModel(IAssessable $assessable, $sequence)
    {
        $dataModel = $this->translator->translateToDataModel($assessable, $sequence);
        // Turn the data model inside out
        // TODO try to remove this, and update translator to accept Model objects directly
        $model = $dataModel->getAssessmentModel();
        $model->setSubObject($dataModel);
        return $model;
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
}