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
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Learning\Assessment\DataModel\Translator\IAssessmentTypeTranslator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
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
     */
    public function __construct()
    {
        // TODO inject all of this
        $this->markSchemeFactory = new MarkSchemeFactory();
        $this->subTranslators[]  = new Translator\Task();
        $this->subTranslators[]  = new Translator\Quiz();
        $this->subTranslators[]  = new Translator\DueWork();
        $this->subTranslators[]  = new Translator\Project($this);
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
        // TODO figure out how to pass in the assessable's container
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
        return $assessable;
    }
}
