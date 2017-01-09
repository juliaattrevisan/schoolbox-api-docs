<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Schoolbox\Assessment\Component\SubmissionBox as DueWorkModel;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Learning\Assessment\DataModel\Translator\IAssessmentTypeTranslator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Translator as UnitImplementationTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
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
     * Sub-translators
     *
     * @var IAssessmentTypeTranslator[]
     */
    protected $subTranslators = [];

    /**
     * Constructor
     *
     * @param MarkSchemeFactory            $markSchemeFactory
     */
    public function __construct(MarkSchemeFactory $markSchemeFactory)
    {
        $this->markSchemeFactory = $markSchemeFactory;
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
     * @return IAssessableDataModel
     */
    public function translateToDataModel(IAssessable $assessment)
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
            ->setDuration($assessment->getDuration())
            ->setWeight($assessment->getWeight())
            ->setSequence($assessment->getSequence())
            ->setCreatedAt($assessment->getCreatedAt())
            ->setUpdatedAt($assessment->getUpdatedAt())
            ->setDeletedAt($assessment->getDeletedAt())
        ;

        // Translate specification assessment if there is one
        $specification = $assessment->getSpecificationAssessment();
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
        $context = $assessment->getContext();
        if ($context instanceof Folder) {
            $assessmentModel->setAssessmentContext($context);

        } elseif ($context instanceof UnitSpecification) {
            $assessmentModel->setAssessmentContext($context);

        } elseif ($context instanceof UnitImplementation) {
            $assessmentModel->setAssessmentContext($context);

        } elseif ($context instanceof Project) {
            // Can't use translator for projects - it will attempt to translate its sub-assessments,
            // thus causing an infinite loop.
            // Set a simplified version instead.
            $assessmentModel->setAssessmentContext(
                (new ProjectModel())
                    ->setAssessmentModel((new AssessmentModel())->setId($context->getId()))
            );

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
            ->setSequence($dataModel->getAssessmentModel()->getSequence())
            ->setTitle($dataModel->getAssessmentModel()->getTitle())
            ->setDescription($dataModel->getAssessmentModel()->getDescription())
            ->setDuration($dataModel->getAssessmentModel()->getDuration())
            ->setWeight($dataModel->getAssessmentModel()->getWeight())
            ->setPublishAt($dataModel->getAssessmentModel()->getPublishDate())
            ->setOpenAt($dataModel->getAssessmentModel()->getOpenDate())
            ->setDueAt($dataModel->getAssessmentModel()->getDueDate())
            ->setCloseAt($dataModel->getAssessmentModel()->getCloseDate())
            ->setCreatedAt($dataModel->getAssessmentModel()->getCreatedAt())
            ->setUpdatedAt($dataModel->getAssessmentModel()->getUpdatedAt())
            ->setDeletedAt($dataModel->getAssessmentModel()->getDeletedAt())
        ;

        // Translate specification assessment if there is one
        $specification = $dataModel->getAssessmentModel()->getSpecificationAssessment();
        if ($specification instanceof Model) {
            // Turn it inside out, because that's the way I got it
            $model = $specification->getSubObject();
            $model->setAssessmentModel($specification);

            // Holy recursion batman
            $assessable->setSpecificationAssessment($this->translateToDomainModel($model));
        }

        // Translate assessment context
        $context = $dataModel->getAssessmentModel()->getAssessmentContext();
        if ($context instanceof Folder) {
            $assessable->setContext($context);

        } elseif ($context instanceof UnitSpecification) {
            $assessable->setContext($context);

        } elseif ($context instanceof UnitImplementation) {
            $assessable->setContext($context);

        } elseif ($context instanceof ProjectModel) {
            // Need to be careful when translating project:
            // any constituent assessments need to be removed, otherwise they
            // will attempt to hydrate their children, which will hydrate this
            // again, and so on...
            $context->setAssessments(new Collection());
            $translatedContext = $this->translateToDomainModel($context);
            if ($translatedContext instanceof Project) {
                $assessable->setContext($translatedContext);
            } else {
                throw new UnexpectedValueException('$dataModel is a project, but its context is not for some reason!');
            }

        } else {
            throw new UnexpectedValueException('$dataModel has no context (folder, unit, unit spec or project)');
        }


        return $assessable;
    }
}
