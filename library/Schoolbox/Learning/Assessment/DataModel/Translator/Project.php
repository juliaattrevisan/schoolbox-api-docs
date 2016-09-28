<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Learning\Assessment\DataModel\Translator as BaseTranslator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project as ProjectObject;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use UnexpectedValueException;

/**
 * Sub-translator for project specific fields.
 */
class Project implements IAssessmentTypeTranslator
{
    /**
     * @var BaseTranslator
     */
    protected $baseTranslator;

    /**
     * Constructor
     *
     * @param BaseTranslator $baseTranslator A translator that can translate sub-assessments of a project
     */
    public function __construct(BaseTranslator $baseTranslator)
    {
        $this->baseTranslator = $baseTranslator;
    }

    /**
     * @inheritdoc
     */
    public function translateToDataModel(IAssessable $assessment)
    {
        if ($assessment instanceof ProjectObject) {
            // convert to project's model
            $model     = new ProjectModel();
            $subModels = new Collection();
            $i         = 0;
            foreach ($assessment->getAssessables() as $assessable) {
                $subModel = $this->baseTranslator->translateToDataModel($assessable, $i++);
                // You can't have projects within projects
                if ($subModel instanceof ProjectModel) {
                    throw new UnexpectedValueException('A project may not contain another project');
                }
                $subModels->add($subModel);
            }
            $model->setAssessments($subModels);

            // Translate summator
            if ($assessment->getSummator() instanceof Average) {
                $model->setSummator(ProjectModel::SUMMATOR_AVERAGE);
            } elseif ($assessment->getSummator() instanceof WeightedAverage) {
                $model->setSummator(ProjectModel::SUMMATOR_WEIGHTED_AVERAGE);
            } elseif ($assessment->getSummator() instanceof Minimum) {
                $model->setSummator(ProjectModel::SUMMATOR_MINIMUM);
            } elseif ($assessment->getSummator() instanceof Maximum) {
                $model->setSummator(ProjectModel::SUMMATOR_MAXIMUM);
            }

            return $model;
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function translateToDomainModel(IAssessableDataModel $model)
    {
        if ($model instanceof ProjectModel) {

            // Project-specific fields

            // Figure out which summator to make
            if ($model->getSummator() == ProjectModel::SUMMATOR_AVERAGE) {
                $summator = new Average();
            } elseif ($model->getSummator() == ProjectModel::SUMMATOR_WEIGHTED_AVERAGE) {
                $summator = new WeightedAverage();
            } elseif ($model->getSummator() == ProjectModel::SUMMATOR_MINIMUM) {
                $summator = new Minimum();
            } elseif ($model->getSummator() == ProjectModel::SUMMATOR_MAXIMUM) {
                $summator = new Maximum();
            } else {
                throw new UnexpectedValueException('$model->getSummator() is not a recognised summator type');
            }

            $assessable = new ProjectObject($summator);

            // Convert sub-assessments
            foreach ($model->getAssessments() as $subModel) {
                if ($subModel instanceof ProjectModel) {
                    throw new UnexpectedValueException('A project may not contain another project');
                }
                $assessable->getAssessables()->add($this->baseTranslator->translateToDomainModel($subModel));
            }

            // Convert mark type
            $assessable->setMarkType(
                (new MarkType())
                    ->setId($model->getMarkType()->getId())
            );

            return $assessable;
        }
        return null;
    }
}
