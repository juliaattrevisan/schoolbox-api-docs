<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Schoolbox\Assessment\Component\OnlineAssessment as QuizModel;
use Schoolbox\Assessment\Component\SubmissionBox as DueWorkModel;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DataModel\Translator\IAssessmentTypeTranslator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementationModel;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Translator as UnitImplementationTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecificationModel;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Translator as UnitSpecificationTranslator;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Resource\Folder\Model as Folder;
use UnexpectedValueException;

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
     * Translator for unit specifications.
     *
     * Used in this class's translators to convert contexts.
     *
     * @var UnitSpecificationTranslator
     */
    protected $unitSpecificationTranslator;

    /**
     * Translator for unit implementations.
     *
     * Used in this class's translators to convert contexts.
     *
     * @var UnitImplementationTranslator
     */
    protected $unitImplementationTranslator;

    /**
     * Sub-translators
     *
     * @var IAssessmentTypeTranslator[]
     */
    protected $subTranslators = [];

    /**
     * Constructor
     *
     * @param MarkSchemeFactory            $markSchemeFactory
     * @param UnitSpecificationTranslator  $unitSpecificationTranslator
     * @param UnitImplementationTranslator $unitImplementationTranslator
     */
    public function __construct(
        MarkSchemeFactory $markSchemeFactory,
        UnitSpecificationTranslator $unitSpecificationTranslator,
        UnitImplementationTranslator $unitImplementationTranslator
    ) {
        $this->markSchemeFactory            = $markSchemeFactory;
        $this->unitSpecificationTranslator  = $unitSpecificationTranslator;
        $this->unitImplementationTranslator = $unitImplementationTranslator;
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
     * Translates the Assessment business domain model to a data model.
     *
     * @param IAssessable $assessment
     * @param int         $sequence   The position of this assessment within its container
     * @return IAssessableDataModel
     */
    public function translateToDataModel(IAssessable $assessment, $sequence)
    {
        // Extract assessment base model
        // TODO figure out how to pass in the assessable's container (folder, course spec, course impl)
        $assessmentModel = new Model();
        $assessmentModel
            ->setId($assessment->getId())
            ->setTitle($assessment->getTitle())
            ->setDescription($assessment->getDescription())
            ->setPublishDate($assessment->getPublishAt())
            ->setOpenDate($assessment->getOpenAt())
            ->setDueDate($assessment->getDueAt())
            ->setCloseDate($assessment->getCloseAt())
            ->setWeight($assessment->getWeight())
            ->setSequence($sequence)
            ->setCreatedAt($assessment->getCreatedAt())
            ->setUpdatedAt($assessment->getUpdatedAt())
            ->setDeletedAt($assessment->getDeletedAt())
        ;

        // Translate assessment context
        // TODO consider moving this to a translator
        // TODO add project context
        $context = $assessment->getContext();
        if ($context instanceof Folder) {
            $assessmentModel->setAssessmentContext($context);
        } elseif ($context instanceof UnitSpecification) {
            // Can't use translator here.
            // UnitSpecificationTranslator->translateToDataModel requires a course spec I don't have.
            $assessmentModel->setAssessmentContext((new UnitSpecificationModel())->setId($context->getId()));
        } elseif ($context instanceof UnitImplementation) {
            $assessmentModel->setAssessmentContext($this->unitImplementationTranslator->translateToDataModel($context));
        } else {
            throw new UnexpectedValueException('$assessment has no context (folder, unit, unit spec or project)');
        }

        // Attempt to translate tasks using the sub-translators
        // TODO figure out which fields on assessable sub-objects are now redundant
        // TODO figure out how to translate participants
        foreach ($this->subTranslators as $subTranslator) {
            $model = $subTranslator->translateToDataModel($assessment);
            if ($model instanceof IAssessableDataModel) {
                $model->setAssessmentModel($assessmentModel);
                return $model;
            }
        }

        throw new UnexpectedValueException('$assessment is not a recognised assessment type');
    }

    /**
     * Translates the data model to a domain model.
     *
     * @param IAssessableDataModel $dataModel
     * @return IAssessable
     * @throws UnexpectedValueException if $dataModel wasn't able to be translated
     */
    public function translateToDomainModel(IAssessableDataModel $dataModel)
    {
        $assessable = null;
        foreach ($this->subTranslators as $subTranslator) {
            $assessable = $subTranslator->translateToDomainModel($dataModel);
            if ($assessable instanceof IAssessable) {
                break;
            }
        }

        if (!$assessable instanceof IAssessable) {
            throw new UnexpectedValueException('$dataModel is not a recognised assessment data model');
        }

        // Generic fields
        // Where there's both a version in assessment model and base model,
        // use the version in assessment model.
        if (!$dataModel->getAssessmentModel() instanceof AssessmentModel) {
            throw new UnexpectedValueException('$dataModel has no assessment model');
        }

        $assessable
            ->setMarkScheme($this->markSchemeFactory->spawnMarkSchemeFor($assessable))
            ->setId($dataModel->getAssessmentModel()->getId())
            ->setTitle($dataModel->getAssessmentModel()->getTitle())
            ->setDescription($dataModel->getAssessmentModel()->getDescription())
            ->setWeight($dataModel->getAssessmentModel()->getWeight())
            ->setPublishAt($dataModel->getAssessmentModel()->getPublishDate())
            ->setOpenAt($dataModel->getAssessmentModel()->getOpenDate())
            ->setDueAt($dataModel->getAssessmentModel()->getDueDate())
            ->setCloseAt($dataModel->getAssessmentModel()->getCloseDate())
            ->setCreatedAt($dataModel->getAssessmentModel()->getCreatedAt())
            ->setUpdatedAt($dataModel->getAssessmentModel()->getUpdatedAt())
            ->setDeletedAt($dataModel->getAssessmentModel()->getDeletedAt())
        ;

        // Translate assessment context
        // TODO add project context
        $context = $dataModel->getAssessmentModel()->getAssessmentContext();
        if ($context instanceof Folder) {
            $assessable->setContext($context);
        } elseif ($context instanceof UnitSpecificationModel) {
            $assessable->setContext($this->unitSpecificationTranslator->translateToDomainModel($context));
        } elseif ($context instanceof UnitImplementationModel) {
            $assessable->setContext($this->unitImplementationTranslator->translateToDomainModel($context));
        } else {
            throw new UnexpectedValueException('$dataModel has no context (folder, unit, unit spec or project)');
        }

        return $assessable;
    }
}
