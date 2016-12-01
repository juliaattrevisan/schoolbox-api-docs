<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
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
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Percentage;
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
            $model = (new ProjectModel())
                ->setTitle($assessment->getTitle())
                ->setDescription($assessment->getDescription())
                ->setWeight($assessment->getWeight())
                ->setMarkType(new MarkType())
                ->setGradeGroup(new GradeGroup())
                ->setOwner($assessment->getCreator())
            ;
            $subModels = new Collection();
            $i         = 0;
            foreach ($assessment->getAssessables() as $subAssessment) {
                // You can't have projects within projects
                if ($subAssessment instanceof ProjectObject) {
                    throw new UnexpectedValueException('A project may not contain another project');
                }

                // The sub-assessment's context is the project
                $subAssessment->setContext($assessment);
                $subModel = $this->baseTranslator->translateToDataModel($subAssessment, $i++);
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

            // Translate mark scheme
            // TODO move this to the mark scheme translator
            $markScheme = $assessment->getMarkScheme();

            if ($markScheme instanceof Percentage) {
                $model->getMarkType()->setId(MarkType::TYPE_PERCENT);

            } elseif ($markScheme instanceof LetterGrade) {
                $model->setGradeGroup($markScheme->getGradeGroup());
                $model->getMarkType()->setId(MarkType::TYPE_LETTER);

            } elseif ($markScheme instanceof None) {
                $model->getMarkType()->setId(MarkType::TYPE_NONE);

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

            $assessable = (new ProjectObject($summator))
                ->setCreator($model->getOwner());

            // Convert sub-assessments
            foreach ($model->getAssessments() as $subModel) {
                if ($subModel instanceof ProjectModel) {
                    throw new UnexpectedValueException('A project may not contain another project');
                }
                // The sub-assessment's context is the project
                $subModel->getAssessmentModel()->setAssessmentContext($model);
                $assessable->getAssessables()->add($this->baseTranslator->translateToDomainModel($subModel));
            }

            // Convert mark type and grade group
            $assessable->setMarkType(
                (new MarkType())
                    ->setId($model->getMarkType()->getId())
            );
            if ($model->getGradeGroup() instanceof GradeGroup) {
                $assessable->setGradeGroup($model->getGradeGroup());
            }

            return $assessable;
        }
        return null;
    }
}
