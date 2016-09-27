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
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Resource\Storage\Model as StorageModel;
use UnexpectedValueException;

/**
 * Translation class between an Assessment and its data model.
 */
class Translator
{
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
        // TODO add created/updated/deleted dates
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
        ;

        // The translation depends on the type of $assessment.
        // Probably doesn't always return a Model either, it will return an old
        // Quiz/DueWork/Task/Project model, with an Assessment attached.
        // TODO figure out which fields on assessable sub-objects are now redundant
        // TODO figure out how to translate participants
        if ($assessment instanceof Project) {
            // convert to project's model
            $model     = new ProjectModel();
            $subModels = new Collection();
            $i         = 0;
            foreach ($assessment->getAssessables() as $assessable) {
                $subModel = $this->translateToDataModel($assessable, $i++);
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

        } elseif ($assessment instanceof DueWork) {
            // convert to due work's model
            $model = new DueWorkModel();
            $model
                ->setAssessmentModel($assessmentModel)
                ->setName($assessment->getTitle())
                ->setDescription($assessment->getDescription())
                ->setPublishDate($assessment->getPublishAt())
                ->setOpenDate($assessment->getOpenAt())
                ->setDueDate($assessment->getDueAt())
                ->setLateDate($assessment->getCloseAt())
                ->setWeight($assessment->getWeight())
                ->setSkipUnsubmitted($assessment->getShowSubmittedOnly())
                ->setAcceptOnline($assessment->isAcceptSingle() || $assessment->isAcceptMulti())
                ->setAcceptMultiple($assessment->isAcceptMulti())
                ->setUseRubric($assessment->hasRubric())
                ->setOwner($assessment->getCreator())
            ;

            // Translate files
            // TODO consider whether the DueWorkFile model is required - should Storage be used directly?
            foreach ($assessment->getAllFiles() as $file) {
                $model->addAttachment(
                    (new StorageModel())
                        ->setHash($file->getHash())
                        ->setFilename($file->getFilename())
                );
            }

            // Translate work type
            $model->setWorkType(
                (new DueWorkModel\WorkType())
                    ->setId($assessment->getType()->getId())
                    ->setName($assessment->getType()->getName())
            );

            // TODO figure out mark visibility - it's likely obsolete as feedback is manually published
            // TODO figure out rubric result set
            // TODO figure out mark per rubric group
            // TODO set model's grade group, mark type, max mark based on mark scheme
            return $model;

        } elseif ($assessment instanceof Quiz) {
            // convert to quiz's model
            $model = new QuizModel();
            $model
                ->setAssessmentModel($assessmentModel)
                ->setAvailability(QuizModel::AVAILABILITY_ASSESS)
                ->setTitle($assessment->getTitle())
                ->setDescription($assessment->getDescription())
                ->setStart($assessment->getOpenAt())
                ->setFinish($assessment->getDueAt())
                ->setWeight($assessment->getWeight())
                ->setAnonymousResult(false)
                ->setUser($assessment->getCreator())
                ->setCompletionMessage($assessment->getCompletionMessage())
                ->setDuration($assessment->getDuration())
                ->setMaxAttempts($assessment->getMaxAttempts())
            ;
            // TODO figure out mark visibility - it's likely obsolete as feedback is manually published
            // TODO add notify author? (I am reasonably sure this field is obsolete)
            return $model;

        } elseif ($assessment instanceof Task) {
            // convert to task's model
            // task currently has its own ID, but that will be handled in the mapper proxy
            $model = new TaskModel();
            $model
                ->setAssessmentModel($assessmentModel)
                ->setTitle($assessment->getTitle())
                ->setOrder(0)
                ->setOwner($assessment->getCreator())
                ->setLink($assessment->getLink())
                ->setRequiresConfirmation(!$assessment->isSelfMarkable())
            ;
            return $model;

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
        if ($dataModel instanceof TaskModel) {
            // Task-specific fields
            $assessable = (new Task())
                ->setLink($dataModel->getLink())
                ->setSelfMarkable(!$dataModel->getRequiresConfirmation())
                ->setCreator($dataModel->getOwner())
            ;

        } elseif ($dataModel instanceof QuizModel) {
            // Quiz-specific fields
            $assessable = (new Quiz())
                ->setMaxAttempts($dataModel->getMaxAttempts())
                ->setDuration($dataModel->getDuration())
                ->setCompletionMessage($dataModel->getCompletionMessage())
                ->setCreator($dataModel->getUser())
            ;

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
            ->setId($dataModel->getAssessmentModel()->getId())
            ->setTitle($dataModel->getAssessmentModel()->getTitle())
            ->setDescription($dataModel->getAssessmentModel()->getDescription())
            ->setWeight($dataModel->getAssessmentModel()->getWeight())
            ->setPublishAt($dataModel->getAssessmentModel()->getPublishDate())
            ->setOpenAt($dataModel->getAssessmentModel()->getOpenDate())
            ->setDueAt($dataModel->getAssessmentModel()->getDueDate())
            ->setCloseAt($dataModel->getAssessmentModel()->getCloseDate())
        ;
        return $assessable;
    }
}
