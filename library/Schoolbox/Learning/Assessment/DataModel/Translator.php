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
     * We need a mechanism to prevent projects transforming their children, who each transform their parent, Ad infinitum.
     * We need to ensure logically unique instances are respected, and also aim to translate assessments once only.
     * By always translating from root down, we can achieve order-N^2 for a theoretical order-N problem. We will
     * need a secondary cache to achieve order-N translation of random assessment collections that contain projects.
     * // TODO see SA-5744.
     *
     * Translating up the object graph is problematic. It requires placing the current assessment in its parent context's assessment
     * collection at the same index. This becomes a N^2 operation, and N^3 for collections containing projects (bad).
     * Translating down the graph is order-N, and N^2 for collections containing projects.
     *
     * For now, we do need to re-transform relationships & properties even if entity is cached because those relationships may have changed
     *
     * @param IAssessable $domainObject
     * @return IAssessableDataModel
     *
     * @throws UnexpectedValueException If assessment does not have a context
     * @throws LogicException If cyclic graph detected
     */
    public function translateToDataModel(IAssessable $domainObject): IAssessableDataModel
    {
        // Always translate from top-down.
        // This means we can always iterate a project's subAssessments in order(N) time.
        $rootAssessable = $domainObject;
        $levelsClimbed  = 0;
        while ($rootAssessable->getContext() instanceof Project) {
            $levelsClimbed++;
            if ($levelsClimbed > $this->maxProjectLevels) {
                throw new LogicException("Unable to find root project after $levelsClimbed iterations. You have a cycle or too many project nesting levels");
            }
            $rootAssessable = $rootAssessable->getContext();
        }
        $this->translateToDataModelRecursive($rootAssessable);
        // If this line throws a NPE, most likely your model graph is missing the relationship from root to this node.
        return $this->dataModelCache[spl_object_hash($domainObject)];
    }


    /**
     * Translates the data model to a domain model.
     *
     * @param IAssessableDataModel $dataModel
     * @return IAssessable
     * @throws UnexpectedValueException if $dataModel wasn't able to be translated
     * @throws \DomainException If MarkType is invalid for the assessment
     * @throws \InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     */
    public function translateToDomainModel(IAssessableDataModel $dataModel): IAssessable
    {
        // Always translate from top-down.
        // This means we can always iterate a project's subAssessments in order(N) time.
        $root = $dataModel;
        $levelsClimbed  = 0;
        while ($root->getAssessmentModel() instanceof AssessmentModel && $root->getAssessmentModel()->getContextProject() instanceof ProjectModel) {
            $levelsClimbed++;
            if ($levelsClimbed > $this->maxProjectLevels) {
                throw new LogicException("Unable to find root project after $levelsClimbed iterations. You have a cycle or too many project nesting levels");
            }
            $root = $root->getAssessmentModel()->getContextProject();
        }
        $this->translateToDomainModelRecursive($root);
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
            ->setWeight($domainObject->getWeight())
            ->setSequence($domainObject->getSequence())
            ->setWorkType($domainObject->getWorkType())
            ->setCreatedAt($domainObject->getCreatedAt())
            ->setUpdatedAt($domainObject->getUpdatedAt())
            ->setDeletedAt($domainObject->getDeletedAt())
            ->setCurriculumNodes($domainObject->getCurriculumNodes())
            ->setExternalId($domainObject->getExternalId());

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
            $dataModel->setAssessments(new GenericCollection(AssessmentModel::class));
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

        // Nullable properties
        $domainObject->setSequence($assessmentModel->getSequence())
                     ->setWorkType($assessmentModel->getWorkType())
                     ->setTitle($assessmentModel->getTitle())
                     ->setDescription($assessmentModel->getDescription())
                     ->setDefaultOpenSpan($assessmentModel->getDefaultOpenSpan())
                     ->setDeletedAt($assessmentModel->getDeletedAt())
                     ->setCurriculumNodes($assessmentModel->getCurriculumNodes())
                     ->setExternalId($assessmentModel->getExternalId());

        // Non-nullable properties
        $markScheme = $this->markSchemeFactory->spawnMarkSchemeFor($domainObject);
        null === $markScheme || $domainObject->setMarkScheme($markScheme);
        null === $assessmentModel->getWeight() || $domainObject->setWeight($assessmentModel->getWeight());
        null === $assessmentModel->getPublishDate() || $domainObject->setPublishAt($assessmentModel->getPublishDate());
        null === $assessmentModel->getOpenDate() || $domainObject->setOpenAt($assessmentModel->getOpenDate());
        null === $assessmentModel->getDueDate() || $domainObject->setDueAt($assessmentModel->getDueDate());
        null === $assessmentModel->getCloseDate() || $domainObject->setCloseAt($assessmentModel->getCloseDate());
        null === $assessmentModel->getCreatedAt() || $domainObject->setCreatedAt($assessmentModel->getCreatedAt());
        null === $assessmentModel->getUpdatedAt() || $domainObject->setUpdatedAt($assessmentModel->getUpdatedAt());
        null === $assessmentModel->getCurriculumNodes() || $domainObject->setCurriculumNodes($assessmentModel->getCurriculumNodes());

        // Translate specification assessment if there is one
        $specification = $dataModel->getAssessmentModel()->getSpecificationAssessment();
        if ($specification instanceof Model) {
            // Turn it inside out, because that's the way I got it
            $model = $specification->getSubObject();
            $model->setAssessmentModel($specification);

            // Holy recursion batman
            $domainObject->setSpecificationAssessment($this->translateToDomainModel($model));
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
                $domainObject->setContext($projectDomainModel);
            }
        }

        // Ideally this would exist in the Project translator, but that would require making this method public.
        // That is too dangerous, best to perform an instanceof here.
        if ($dataModel instanceof ProjectModel && $dataModel->getAssessments() !== null) {
            /** @var Project $domainObject */
            $domainObject->setAssessables(new GenericCollection(IAssessable::class));
            foreach ($dataModel->getAssessments() as $idx => $dataChild) {
                $domainChild = $this->translateToDomainModelRecursive($dataChild->getSubObject(), $depth + 1);
                $domainChild->setContext($domainObject);
                $domainObject->getAssessables()[$idx] = $domainChild;
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
