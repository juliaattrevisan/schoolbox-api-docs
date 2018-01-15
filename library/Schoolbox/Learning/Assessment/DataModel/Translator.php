<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Learning\Assessment\DataModel\Translator\IAssessmentTypeTranslator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
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
        int $maxProjectLevels = 10
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
    public function addSubTranslator(IAssessmentTypeTranslator $subTranslator)
    {
        $this->subTranslators[] = $subTranslator;
        return $this;
    }


    /**
     * @param IAssessable $domainObject
     * @return IAssessmentTypeTranslator
     * @throws UnexpectedValueException
     */
    private function getSubTranslatorDomain(IAssessable $domainObject)
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
    private function getSubTranslatorData(IAssessableDataModel $dataModel)
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
     * Translates the Assessment business domain model to a data model.
     *
     * We need a mechanism to prevent projects transforming their children, who each transform their parent, Ad infinitum.
     * We can either short-circuit at parent->child->parent or child->parent->child. If we choose the latter we would need
     * to detect the position of items in the children collection to ensure consistent indexing between translation layers.
     * This is an order(N) operation (and also fails if the same item instance is present multiple times),
     * whereas settings a child's parent is order(1). Hence the former approach is used.
     *
     * We do need to re-transform relationships & properties even if entity is cached because those relationships may have changed
     *
     * @param IAssessable $domainObject
     * @param int         $depth
     * @param bool        $translateProjectContext
     * @return IAssessableDataModel
     *
     * @throws UnexpectedValueException If assessment does not have a context
     * @throws LogicException If cyclic graph detected
     */
    public function translateToDataModel(IAssessable $domainObject, int $depth = 0, bool $translateProjectContext = true): IAssessableDataModel
    {
        $this->checkProjectNestingLevel($depth);

        // Attempt to translate assessments using the sub-translators
        $subTranslator = $this->getSubTranslatorDomain($domainObject);
        // TODO figure out which fields on assessable sub-objects are now redundant
        // TODO figure out how to translate participants

        // Have we not yet translated this domain object?
        $domainHash = spl_object_hash($domainObject);
        if (!$this->dataModelCache->keyExists($domainHash)) {
            // Insert new dataModel in data cache
            $this->dataModelCache[$domainHash] = $this->createDataModel($subTranslator, $domainObject);

            // Insert domainObject in the domainObject cache so subsequent data-to-domain translation fetches this instance
            $this->domainObjectCache[spl_object_hash($this->dataModelCache[$domainHash])] = $domainObject;
        }
        $dataModel       = $this->dataModelCache[$domainHash];
        $assessmentModel = $dataModel->getAssessmentModel();

        $subTranslator->translateToDataModel($domainObject, $dataModel, $depth);

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
        // TODO add project context
        $context = $domainObject->getContext();
        if ($context instanceof Folder) {
            $assessmentModel->setAssessmentContext($context);

        } elseif ($context instanceof UnitSpecification) {
            $assessmentModel->setAssessmentContext($context);

        } elseif ($context instanceof UnitImplementation) {
            $assessmentModel->setAssessmentContext($context);

        } elseif ($context instanceof Project) {
            // Does the domainObject have a parent but the dataModel does not?
            if ($translateProjectContext && !$dataModel->getAssessmentModel()->getContextProject()) {
                $dataModel->getAssessmentModel()->setAssessmentContext($this->translateToDataModel($context, $depth + 1));
            }
        }

        return $dataModel;
    }


    /**
     * Translates the data model to a domain model.
     *
     * @param IAssessableDataModel $dataModel
     * @param int                  $depth
     * @param bool                 $translateParent
     * @return IAssessable
     * @throws UnexpectedValueException if $dataModel wasn't able to be translated
     * @throws \DomainException If MarkType is invalid for the assessment
     * @throws \InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     */
    public function translateToDomainModel(IAssessableDataModel $dataModel, int $depth = 0, bool $translateParent = true): IAssessable
    {
        $this->checkProjectNestingLevel($depth);

        $assessmentModel = $dataModel->getAssessmentModel();
        if (!$assessmentModel instanceof AssessmentModel) {
            throw new UnexpectedValueException('$dataModel has no assessment model');
        }

        $subTranslator = $this->getSubTranslatorData($dataModel);

        // Have we not yet translated this dataModel?
        $dataHash = spl_object_hash($dataModel);
        if (!$this->domainObjectCache->keyExists($dataHash)) {
            $this->domainObjectCache[$dataHash] = $subTranslator->createDomainObject()->setId($assessmentModel->getId());

            // Insert dataModel in the dataModel cache so subsequent domain-to-data translation fetches this instance
            $this->dataModelCache[spl_object_hash($this->domainObjectCache[$dataHash])] = $dataModel;
        }
        $domainObject = $this->domainObjectCache[$dataHash];

        $subTranslator->translateToDomainModel($dataModel, $domainObject);

        // Generic fields
        // Where there's both a version in assessment model and base model,
        // use the version in assessment model.

        $domainObject
            ->setMarkScheme($this->markSchemeFactory->spawnMarkSchemeFor($domainObject))
            ->setSequence($assessmentModel->getSequence())
            ->setWorkType($assessmentModel->getWorkType())
            ->setTitle($assessmentModel->getTitle())
            ->setDescription($assessmentModel->getDescription())
            ->setDefaultOpenSpan($assessmentModel->getDefaultOpenSpan())
            ->setWeight($assessmentModel->getWeight())
            ->setPublishAt($assessmentModel->getPublishDate())
            ->setOpenAt($assessmentModel->getOpenDate())
            ->setDueAt($assessmentModel->getDueDate())
            ->setCloseAt($assessmentModel->getCloseDate())
            ->setCreatedAt($assessmentModel->getCreatedAt())
            ->setUpdatedAt($assessmentModel->getUpdatedAt())
            ->setDeletedAt($assessmentModel->getDeletedAt())
            ->setCurriculumNodes($assessmentModel->getCurriculumNodes());

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
            // Does the data model have a parent but the domain does not?
            if ($translateParent && !$domainObject->getContext()) {
                $translatedContext = $this->translateToDomainModel($context, $depth + 1);
                if ($translatedContext instanceof Project) {
                    $domainObject->setContext($translatedContext);
                } else {
                    throw new UnexpectedValueException('$dataModel is a project, but its context is not for some reason!');
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
