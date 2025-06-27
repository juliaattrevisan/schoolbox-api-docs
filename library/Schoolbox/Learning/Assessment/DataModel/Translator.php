<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Learning\Assessment\DataModel\Translator\IAssessmentTypeTranslator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Resource\Folder\Model as Folder;
use UnexpectedValueException;
use LogicException;

/**
 * Translation class between an Assessment and its data model.
 */
class Translator
{
    /**
     * By default, only permit 2 levels (ie sub-assessments inside a project)
     *
     * @const int
     */
    public const MAX_PROJECT_LEVELS_DEFAULT = 2;

    /**
     * Factory for mark schemes for assessables.
     *
     * @var MarkSchemeFactory
     */
    protected $markSchemeFactory;

    /**
     * Sub-translators
     *
     * @var IAssessmentTypeTranslator[]
     */
    protected $subTranslators = [];

    /**
     * @var Mapper
     */
    private $assessmentMapper;


    /** @var IAssessableDataModel[]|GenericCollection */
    private $dataModelCache;

    /** @var IAssessable[]|GenericCollection */
    private $domainObjectCache;

    /**
     * How many levels of projects-in-projects are supported?
     * If we reach exceed this level we can conclude we have a cyclic graph.
     *
     * @var int
     */
    private $maxProjectLevels;


    /**
     * Constructor
     *
     * @param MarkSchemeFactory $markSchemeFactory
     * @param Mapper            $assessmentMapper
     * @param int               $maxProjectLevels
     */
    public function __construct(
        MarkSchemeFactory $markSchemeFactory,
        Mapper $assessmentMapper,
        int $maxProjectLevels
    ) {
        $this->markSchemeFactory = $markSchemeFactory;
        $this->assessmentMapper  = $assessmentMapper;
        $this->maxProjectLevels  = $maxProjectLevels;
        $this->dataModelCache    = new GenericCollection(IAssessableDataModel::class);
        $this->domainObjectCache = new GenericCollection(IAssessable::class);
    }


    /**
     * Adds a sub-translator which is able to handle a particular variant of
     * IAssessable or IAssessableDataModel.
     *
     * @param IAssessmentTypeTranslator $subTranslator
     * @return static
     */
    public function addSubTranslator(IAssessmentTypeTranslator $subTranslator): self
    {
        $this->subTranslators[] = $subTranslator;
        return $this;
    }


    /**
     * Translates an assessment DomainObject to a DataModel
     *
     * @param IAssessable $domainObject The domain object to translate.
     * @param int         $depth        The current depth of translation.
     *                                  This translator will translate the context project of `$domainObject`
     *                                  recursively: if it translates more than `$this->maxProjectLevels` down, this
     *                                  will detect a cyclic graph and raise a `LogicException`.
     * @return IAssessableDataModel
     *
     * @throws UnexpectedValueException If assessment does not have a context
     * @throws LogicException If cyclic graph detected
     */
    public function translateToDataModel(IAssessable $domainObject, int $depth = 1): IAssessableDataModel
    {
        if ($depth > $this->maxProjectLevels) {
            throw new LogicException("Unable to find root project after $depth iterations. You have a cycle or too many project nesting levels");
        }

        // Is `$domainObject` within a project?
        // If so, translate the project first so that `$domainObject` has access
        // to its fully-realised project
        $context = $domainObject->getContext();
        if ($context instanceof Project) {
            if (!isset($this->dataModelCache[spl_object_hash($context)])) {
                $this->translateToDataModel($context, $depth + 1);
            }
        }

        /* Ignore the random andy logic about $rootAssessable
         * and just translate this $domainObject like we wanted
         */
        $this->translateToDataModelRecursive($domainObject);
        // If this line throws a NPE, most likely your model graph is missing the relationship from root to this node.
        return $this->dataModelCache[spl_object_hash($domainObject)];
    }


    /**
     * Translates the data model to a domain model.
     *
     * @param IAssessableDataModel $dataModel The data model to translate.
     * @param int                  $depth     The current depth of translation.
     *                                        This translator will translate the context project of `dataModel`
     *                                        recursively: if it translates more than `$this->maxProjectLevels` down,
     *                                        this will detect a cyclic graph and raise a `LogicException`.
     * @return IAssessable
     * @throws UnexpectedValueException if $dataModel wasn't able to be translated
     * @throws \DomainException If MarkType is invalid for the assessment
     * @throws \InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     */
    public function translateToDomainModel(IAssessableDataModel $dataModel, int $depth = 1): IAssessable
    {
        if ($depth > $this->maxProjectLevels) {
            throw new LogicException("Unable to find root project after $depth iterations. You have a cycle or too many project nesting levels");
        }

        // Is `$dataModel` within a project?
        // If so, translate the project first so that `$dataModel` has access to
        // its fully-realised project
        $assessmentModel = $dataModel->getAssessmentModel();
        if (!$assessmentModel instanceof AssessmentModel) {
            throw new UnexpectedValueException('$dataModel has no assessment model');
        }
        $context = $assessmentModel->getAssessmentContext();
        if ($context instanceof ProjectModel) {
            if (!isset($this->domainObjectCache[spl_object_hash($context)])) {
                $this->translateToDomainModel($context, $depth + 1);
            }
        }

        $this->translateToDomainModelRecursive($dataModel);
        // If this line throws a NPE, most likely your model graph is missing the relationship from root to this node.
        return $this->domainObjectCache[spl_object_hash($dataModel)];
    }


    /**
     * @param IAssessable $domainObject
     * @return IAssessmentTypeTranslator
     * @throws UnexpectedValueException
     */
    private function getSubTranslatorDomain(IAssessable $domainObject): IAssessmentTypeTranslator
    {
        foreach ($this->subTranslators as $subTranslator) {
            if ($subTranslator->recognisesDomainObject($domainObject)) {
                return $subTranslator;
            }
        }
        throw new UnexpectedValueException('$assessment is not a recognised assessment type');
    }


    /**
     * @param IAssessableDataModel $dataModel
     * @return IAssessmentTypeTranslator
     * @throws UnexpectedValueException
     */
    private function getSubTranslatorData(IAssessableDataModel $dataModel): IAssessmentTypeTranslator
    {
        foreach ($this->subTranslators as $subTranslator) {
            if ($subTranslator->recognisesDataModel($dataModel)) {
                return $subTranslator;
            }
        }
        throw new UnexpectedValueException('$dataModel is not a recognised assessment type');
    }


    /**
     * @param IAssessmentTypeTranslator $subTranslator
     * @param IAssessable               $domainObject
     * @return IAssessableDataModel
     */
    private function createDataModel(IAssessmentTypeTranslator $subTranslator, IAssessable $domainObject): IAssessableDataModel
    {
        $dataModel       = $subTranslator->createDataModel();
        $assessmentModel = $this->assessmentMapper->createModel($domainObject->getId());
        $dataModel->setAssessmentModel($assessmentModel);
        $assessmentModel->setSubObject($dataModel);
        return $dataModel;
    }

    /**
     * @param int $depth
     * @throws UnexpectedValueException If we have recursed more than the configured level of project
     */
    private function checkProjectNestingLevel(int $depth): void
    {
        if ($depth >= $this->maxProjectLevels) {
            throw new UnexpectedValueException("Invalid graph detected. Recursion depth: $depth");
        }
    }

    /**
     * Recursively descend the assessment tree, translating, caching and constructing translated tree as we go.
     *
     * @param IAssessable $domainObject
     * @param int         $depth
     * @return IAssessableDataModel
     *
     * @throws UnexpectedValueException If assessment does not have a context
     * @throws LogicException If cyclic graph detected
     */
    private function translateToDataModelRecursive(IAssessable $domainObject, int $depth = 1): IAssessableDataModel
    {
        // Only check nesting level if a project, as:
        // * Only projects may contain other assessments
        // * We don't want projects on the bottom level
        // TODO SA-5744 when descending tree during logically atomic assessment collection translation, need to use
        // TODO         a translation id to skip re-translation of entities already translated during the current translation
        // We are also preventing projects within projects in the project mapper presave
        if ($domainObject instanceof Project) {
            $this->checkProjectNestingLevel($depth);
        }

        // Attempt to translate assessments using the sub-translators
        $subTranslator = $this->getSubTranslatorDomain($domainObject);
        // TODO figure out which fields on assessable sub-objects are now redundant
        // TODO figure out how to translate participants

        $dataModel       = $this->getCachedDataModel($domainObject, $subTranslator);
        $assessmentModel = $dataModel->getAssessmentModel();

        $subTranslator->translateToDataModel($domainObject, $dataModel);

        // Extract assessment base model
        // TODO figure out how to pass in the assessable's container (folder, course spec, course impl)
        $assessmentModel
            ->setTitle($domainObject->getTitle())
            ->setDescription($domainObject->getDescription())
            ->setPublishDate($domainObject->getPublishAt())
            ->setOpenDate($domainObject->getOpenAt())
            ->setDueDate($domainObject->getDueAt())
            ->setCloseDate($domainObject->getCloseAt())
            ->setDefaultOpenSpan($domainObject->getDefaultOpenSpan())
            ->setCourseManaged((bool)$domainObject->getCourseManaged())
            ->setSebEnabled((bool)$domainObject->getSebEnabled())
            ->setOriginalAssessmentId($domainObject->getOriginalAssessmentId())
            ->setWeight($domainObject->getWeight())
            ->setSequence($domainObject->getSequence())
            ->setWorkType($domainObject->getWorkType())
            ->setCreatedAt($domainObject->getCreatedAt())
            ->setUpdatedAt($domainObject->getUpdatedAt())
            ->setDeletedAt($domainObject->getDeletedAt())
            ->setCurriculumNodes($domainObject->getCurriculumNodes());

        $dataModel->setAssessmentModel($assessmentModel);

        // Translate specification assessment if there is one
        $specification = $domainObject->getSpecificationAssessment();
        if ($specification instanceof IAssessable) {
            // Go to no great lengths here, as I need only an ID
            $specModel = $this->translateToDataModel($specification);
            // Turn inside out again
            $specAssessment = $specModel->getAssessmentModel();
            $specAssessment->setSubObject($specModel);
            $assessmentModel->setSpecificationAssessment($specAssessment);
        }

        // Translate assessment context
        // TODO consider moving this to a translator
        $context = $domainObject->getContext();
        if ($context instanceof Folder) {
            $assessmentModel->setAssessmentContext($context);
        } elseif ($context instanceof UnitSpecification) {
            $assessmentModel->setAssessmentContext($context);
        } elseif ($context instanceof UnitImplementation) {
            $assessmentModel->setAssessmentContext($context);
        } elseif ($context instanceof Project) {
            // Fetch project from cache, building it if required
            $projectDataModel = $this->getCachedDataModel($context, $this->getSubTranslatorDomain($context));
            if ($projectDataModel instanceof IAssessableDataModel) {
                $assessmentModel->setAssessmentContext($projectDataModel);
            }
        }

        // This would ideally be in the Project translator. But that would require making this method public.
        // The danger of that is worth the instanceof check here.
        if ($domainObject instanceof Project && $domainObject->getAssessables() !== null) {
            /** @var ProjectModel $dataModel */
            if (!$dataModel->getAssessments()) {
                $dataModel->setAssessments(new GenericCollection(AssessmentModel::class));
            }

            foreach ($domainObject->getAssessables() as $idx => $domainChild) {
                $subAssessmentModel = $this->translateToDataModelRecursive($domainChild, $depth + 1)->getAssessmentModel();
                $subAssessmentModel->setContextProject($dataModel);
                $dataModel->getAssessments()[$idx] = $subAssessmentModel;
            }
        }

        return $dataModel;
    }


    /**
     * Returns the domainObjects' dataModel from the cache, inserting if necessary
     *
     * @param IAssessable               $domainObject
     * @param IAssessmentTypeTranslator $subTranslator
     * @return IAssessableDataModel
     */
    private function getCachedDataModel(IAssessable $domainObject, IAssessmentTypeTranslator $subTranslator): IAssessableDataModel
    {
        $domainHash = spl_object_hash($domainObject);
        if (!$this->dataModelCache->keyExists($domainHash)) {
            // Insert new dataModel in data cache
            $this->dataModelCache[$domainHash] = $this->createDataModel($subTranslator, $domainObject);

            // Insert domainObject in the domainObject cache so subsequent data-to-domain translation fetches this instance
            $this->domainObjectCache[spl_object_hash($this->dataModelCache[$domainHash])] = $domainObject;
        }
        return $this->dataModelCache[$domainHash];
    }


    /**
     * Returns the dataModels' domainObject from the cache, inserting if necessary
     *
     * @param IAssessableDataModel      $dataModel
     * @param IAssessmentTypeTranslator $subTranslator
     * @return IAssessable
     */
    private function getCachedDomainObject(IAssessableDataModel $dataModel, IAssessmentTypeTranslator $subTranslator): IAssessable
    {
        // Have we not yet translated this dataModel?
        $dataHash = spl_object_hash($dataModel);
        if (!$this->domainObjectCache->keyExists($dataHash)) {
            // Insert new dataModel in data cache
            $this->domainObjectCache[$dataHash] = $subTranslator->createDomainObject($dataModel->getAssessmentModel()->getId());

            // Insert dataModel in the dataModel cache so subsequent domain-to-data translation fetches this instance
            $this->dataModelCache[spl_object_hash($this->domainObjectCache[$dataHash])] = $dataModel;
        }
        return $this->domainObjectCache[$dataHash];
    }


    /**
     * Translates the data model to a domain model.
     *
     * @param IAssessableDataModel $dataModel
     * @param int                  $depth
     * @return IAssessable
     * @throws UnexpectedValueException if $dataModel wasn't able to be translated
     * @throws \DomainException If MarkType is invalid for the assessment
     * @throws \InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     */
    private function translateToDomainModelRecursive(IAssessableDataModel $dataModel, int $depth = 1): IAssessable
    {
        // Only check nesting level if a project, as:
        // * Only projects may contain other assessments
        // * We don't want projects on the bottom level
        // We are also preventing projects within projects in the project mapper presave
        if ($dataModel instanceof ProjectModel) {
            $this->checkProjectNestingLevel($depth);
        }

        $assessmentModel = $dataModel->getAssessmentModel();
        if (!$assessmentModel instanceof AssessmentModel) {
            throw new UnexpectedValueException('$dataModel has no assessment model');
        }

        $subTranslator = $this->getSubTranslatorData($dataModel);
        $domainObject  = $this->getCachedDomainObject($dataModel, $subTranslator);

        // Copy attributes + relations
        $subTranslator->translateToDomainModel($dataModel, $domainObject);

        // Generic fields
        // Where there's both a version in assessment model and base model,
        // use the version in assessment model.

        // Properties which should be set as is
        $domainObject
            ->setSequence($assessmentModel->getSequence())
            ->setWorkType($assessmentModel->getWorkType())
            ->setTitle($assessmentModel->getTitle())
            ->setDescription($assessmentModel->getDescription())
            ->setDefaultOpenSpan($assessmentModel->getDefaultOpenSpan())
            ->setCourseManaged((bool)$assessmentModel->getCourseManaged())
            ->setSebEnabled((bool)$assessmentModel->getSebEnabled())
            ->setOriginalAssessmentId((int)$assessmentModel->getOriginalAssessmentId())
            ->setWeight($assessmentModel->getWeight())
            ->setPublishAt($assessmentModel->getPublishDate())
            ->setOpenAt($assessmentModel->getOpenDate())
            ->setDueAt($assessmentModel->getDueDate())
            ->setCloseAt($assessmentModel->getCloseDate())
            ->setCreatedAt($assessmentModel->getCreatedAt())
            ->setUpdatedAt($assessmentModel->getUpdatedAt())
            ->setDeletedAt($assessmentModel->getDeletedAt())
        ;

        // Non-nullable properties: should only be set if set in domain object
        $markScheme = $this->markSchemeFactory->spawnMarkSchemeFor($domainObject);
        if ($markScheme !== null) {
            $domainObject->setMarkScheme($markScheme);
        }
        // Instructor mark scheme/mark type is only for due works
        // Ideally this would exist in the DueWork translator, but that would require making this method public.
        // That is too dangerous, best to perform an instanceof here.
        if ($domainObject instanceof \Schoolbox\Learning\Assessment\DueWork\Model) {
            /** @var \Schoolbox\Learning\Assessment\DueWork\Model $dataModel */
            $domainObject->setInstructorMarkType($dataModel->getInstructorMarkType());
            $domainObject->setInstructorMaxMark($dataModel->getInstructorMaxMark());
            $domainObject->setInstructorGradeGroup($dataModel->getInstructorGradeGroup());

            $instructorMarkScheme = $this->markSchemeFactory->spawnInstructorMarkSchemeFor($domainObject);
            if ($instructorMarkScheme !== null) {
                $domainObject->setInstructorMarkScheme($instructorMarkScheme);
            }
        }
        if ($assessmentModel->getCurriculumNodes() !== null) {
            $domainObject->setCurriculumNodes($assessmentModel->getCurriculumNodes());
        }

        // Translate specification assessment if there is one
        $specification = $dataModel->getAssessmentModel()->getSpecificationAssessment();
        if ($specification instanceof Model) {
            // Turn it inside out, because that's the way I got it
            $model = $specification->getSubObject();
            if ($model) {
                $model->setAssessmentModel($specification);

                // Holy recursion batman
                $domainObject->setSpecificationAssessment($this->translateToDomainModel($model));
            }
            // If there's a spec assessment but it has no sub-object:
            // Probably just pretend that it has no spec
        }

        // Translate assessment context
        $context = $dataModel->getAssessmentModel()->getAssessmentContext();
        if ($context instanceof Folder) {
            $domainObject->setContext($context);
        } elseif ($context instanceof UnitSpecification) {
            $domainObject->setContext($context);
        } elseif ($context instanceof UnitImplementation) {
            $domainObject->setContext($context);
        } elseif ($context instanceof ProjectModel) {
            // Fetch project from cache, building it if required
            $projectDomainModel = $this->getCachedDomainObject($context, $this->getSubTranslatorData($context));
            if ($projectDomainModel instanceof AssessmentContext) {
                // Also copy project's context from data to domain.
                // The project's context should always be the same object in
                // data and domain: the only case where they aren't is for
                // projects themselves, and projects can't exist in projects.
                $projectContext = $context->getAssessmentModel()->getAssessmentContext();
                if ($projectContext instanceof AssessmentContext) {
                    $projectDomainModel->setContext($projectContext);
                }
                $domainObject->setContext($projectDomainModel);
            }
        }

        // Ideally this would exist in the Project translator, but that would require making this method public.
        // That is too dangerous, best to perform an instanceof here.
        if ($dataModel instanceof ProjectModel && $dataModel->getAssessments() !== null) {
            /** @var Project $domainObject */
            $domainObject->setAssessables(new GenericCollection(IAssessable::class));
            foreach ($dataModel->getAssessments() as $idx => $dataChild) {
                $subModel = $dataChild->getSubObject();
                if ($subModel) {
                    $domainChild = $this->translateToDomainModelRecursive($subModel, $depth + 1);
                    $domainChild->setContext($domainObject);
                    $domainObject->getAssessables()[$idx] = $domainChild;
                }
            }
        }

        // As above:
        // Ideally this would exist in the Lesson Plan translator, but that would require making this method public.
        // That is too dangerous, best to perform an instanceof here.
        if ($dataModel instanceof \Schoolbox\Learning\Assessment\DataModel\LessonPlan\Model && $dataModel->getLinkedAssessments() !== null) {
            /** @var \Schoolbox\Learning\Assessment\LessonPlan\Model $domainObject */
            $domainObject->setLinkedAssessments(new GenericCollection(IAssessable::class));
            foreach ($dataModel->getLinkedAssessments() as $idx => $dataChild) {
                $subModel = $dataChild->getSubObject();
                if ($subModel) {
                    // lesson plans can contain projects, so we need to reset the depth to 1
                    $domainChild = $this->translateToDomainModelRecursive($subModel, 1);
                    $domainObject->getLinkedAssessments()[$idx] = $domainChild;
                }
            }
        }

        return $domainObject;
    }


    /**
     * @param int $maxProjectLevels
     * @return Translator
     */
    public function setMaxProjectLevels(int $maxProjectLevels): self
    {
        $this->maxProjectLevels = $maxProjectLevels;
        return $this;
    }
}
