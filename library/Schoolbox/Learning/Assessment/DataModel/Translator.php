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
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\DueWork\Type as DueWorkType;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkWholeNumber;
use Schoolbox\Resource\Storage\Model as StorageModel;
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
        foreach ($this->subTranslators as $subTranslator) {
            $model = $subTranslator->translateToDataModel($assessment);
            if ($model instanceof IAssessableDataModel) {
                $model->setAssessmentModel($assessmentModel);
                return $model;
            }
        }

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
                ->setMarkType(new DueWorkModel\MarkType())
                ->setGradeGroup(new DueWorkModel\GradeLevel\Group\Model())
            ;

            // Convert a mark type into fields
            // TODO move this to the mark scheme translator
            $markScheme = $assessment->getMarkScheme();
            if ($markScheme instanceof TotalMarkWholeNumber) {
                $model->setMaxMark($markScheme->getTotalMark());
                $model->getMarkType()->setId(DueWorkModel\MarkType::TYPE_TOTAL);

            } elseif ($markScheme instanceof Percentage) {
                $model->getMarkType()->setId(DueWorkModel\MarkType::TYPE_PERCENT);

            } elseif ($markScheme instanceof LetterGrade) {
                $model->setGradeGroup($markScheme->getGradeGroup());
                $model->getMarkType()->setId(DueWorkModel\MarkType::TYPE_LETTER);

            } elseif ($markScheme instanceof None) {
                $model->getMarkType()->setId(DueWorkModel\MarkType::TYPE_NONE);

            }

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
        foreach ($this->subTranslators as $subTranslator) {
            $assessable = $subTranslator->translateToDomainModel($dataModel);
            if ($assessable instanceof IAssessable) {
                break;
            }
        }

        if (!$assessable instanceof IAssessable) {
            if ($dataModel instanceof ProjectModel) {
                // Project-specific fields

                // Figure out which summator to make
                if ($dataModel->getSummator() == ProjectModel::SUMMATOR_AVERAGE) {
                    $summator = new Average();
                } elseif ($dataModel->getSummator() == ProjectModel::SUMMATOR_WEIGHTED_AVERAGE) {
                    $summator = new WeightedAverage();
                } elseif ($dataModel->getSummator() == ProjectModel::SUMMATOR_MINIMUM) {
                    $summator = new Minimum();
                } elseif ($dataModel->getSummator() == ProjectModel::SUMMATOR_MAXIMUM) {
                    $summator = new Maximum();
                } else {
                    throw new UnexpectedValueException('$dataModel->getSummator() is not a recognised summator type');
                }

                $assessable = new Project($summator);

                foreach ($dataModel->getAssessments() as $subModel) {
                    if ($subModel instanceof ProjectModel) {
                        throw new UnexpectedValueException('A project may not contain another project');
                    }
                    $assessable->getAssessables()->add($this->translateToDomainModel($subModel));
                }

                // Convert mark type
                $assessable->setMarkType(
                    (new DueWorkModel\MarkType())
                        ->setId($dataModel->getMarkType()->getId())
                );

            } elseif ($dataModel instanceof QuizModel) {
                // Quiz-specific fields
                $assessable = (new Quiz())
                    ->setMarkType((new DueWorkModel\MarkType())->setId(DueWorkModel\MarkType::TYPE_TOTAL))
                    ->setMaxAttempts($dataModel->getMaxAttempts())
                    ->setDuration($dataModel->getDuration())
                    ->setCompletionMessage($dataModel->getCompletionMessage())
                    ->setCreator($dataModel->getUser())
                ;

                // TODO implement correct setting of max marks
                $assessable->setMaxMark(1);

            } elseif ($dataModel instanceof DueWorkModel) {
                // Due work-specific fields
                $assessable = (new DueWork())
                    ->setShowSubmittedOnly($dataModel->getSkipUnsubmitted())
                    ->setHasRubric($dataModel->getUseRubric())
                    ->setCreator($dataModel->getOwner())
                    ->setMaxMark($dataModel->getMaxMark())
                    ->setGradeGroup($dataModel->getGradeGroup())
                ;

                // Figure out submission visibility
                if ($dataModel->getAcceptOnline()) {
                    if ($dataModel->getAcceptMultiple()) {
                        $assessable->setAcceptsMultiple();
                    } else {
                        $assessable->setAcceptsSingle();
                    }
                } else {
                    $assessable->setAcceptsNone();
                }

                // Convert attachments
                foreach ($dataModel->getAttachments() as $attachment) {
                    $assessable->getAllFiles()->add(
                        (new DueWorkFile())
                            ->setHash($attachment->getHash())
                            ->setFilename($attachment->getFilename())
                    );
                }

                // Convert work type
                $assessable->setType(
                    (new DueWorkType())
                        ->setId($dataModel->getWorkType()->getId())
                        ->setName($dataModel->getWorkType()->getName())
                );

                // Convert mark type
                $assessable->setMarkType(
                    (new DueWorkModel\MarkType())
                        ->setId($dataModel->getMarkType()->getId())
                );
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
