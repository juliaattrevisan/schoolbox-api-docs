<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Learning\Assessment\DataModel\Translator as BaseTranslator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project as ProjectObject;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\ISummator;
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

    /** @var MarkTypeMapper */
    private $markTypeMapper;


    /**
     * Constructor
     *
     * @param BaseTranslator $baseTranslator A translator that can translate sub-assessments of a project
     */
    public function __construct(BaseTranslator $baseTranslator)
    {
        $this->baseTranslator = $baseTranslator;
        $this->markTypeMapper = MarkTypeMapper::getInstance(); // TODO inject
    }


    /**
     * @return IAssessableDataModel
     */
    public function createDataModel(): IAssessableDataModel
    {
        return new ProjectModel();
    }


    /**
     * @inheritdoc
     */
    public function createDomainObject(?int $id): IAssessable
    {
        return (new ProjectObject())->setId($id);
    }


    /**
     * Does this sub-translator recognise this assessable?
     *
     * @param IAssessable $domainObject
     * @return bool
     */
    public function recognisesDomainObject(IAssessable $domainObject): bool
    {
        return $domainObject instanceof ProjectObject;
    }


    /**
     * Does this sub-translator recognise this assessment?
     *
     * @param IAssessableDataModel $dataModel
     * @return bool
     */
    public function recognisesDataModel(IAssessableDataModel $dataModel): bool
    {
        return $dataModel instanceof ProjectModel;
    }


    /**
     * @param IAssessable|ProjectObject         $domainObject
     * @param IAssessableDataModel|ProjectModel $dataModel
     */
    public function translateToDataModel(IAssessable $domainObject, IAssessableDataModel $dataModel): void
    {
        if (null !== $domainObject->getCreator()) {
            $dataModel->setOwner($domainObject->getCreator());
        }

        // Translate summator
        if ($domainObject->getSummator() instanceof Average) {
            $dataModel->setSummator(ProjectModel::SUMMATOR_AVERAGE);
        } elseif ($domainObject->getSummator() instanceof WeightedAverage) {
            $dataModel->setSummator(ProjectModel::SUMMATOR_WEIGHTED_AVERAGE);
        } elseif ($domainObject->getSummator() instanceof Minimum) {
            $dataModel->setSummator(ProjectModel::SUMMATOR_MINIMUM);
        } elseif ($domainObject->getSummator() instanceof Maximum) {
            $dataModel->setSummator(ProjectModel::SUMMATOR_MAXIMUM);
        }

        // Translate mark scheme
        // TODO move this to the mark scheme translator
        $markScheme = $domainObject->getMarkScheme();
        if ($markScheme instanceof Percentage) {
            $dataModel->setMarkType($this->markTypeMapper->createModel(MarkType::TYPE_PERCENT));
        } elseif ($markScheme instanceof LetterGrade) {
            $dataModel->setMarkType($this->markTypeMapper->createModel(MarkType::TYPE_LETTER));
            $dataModel->setGradeGroup($markScheme->getGradeGroup());
        } elseif ($markScheme instanceof None) {
            $dataModel->setMarkType($this->markTypeMapper->createModel(MarkType::TYPE_NONE));
        }
    }


    /**
     * @param IAssessableDataModel|ProjectModel $dataModel
     * @param IAssessable|ProjectObject         $domainObject
     * @throws UnexpectedValueException
     */
    public function translateToDomainModel(IAssessableDataModel $dataModel, IAssessable $domainObject): void
    {
        // Project-specific fields

        // Figure out which summator to make
        $domainObject->setSummator($this->getSummator($dataModel));
        null === $dataModel->getOwner() || $domainObject->setCreator($dataModel->getOwner());


        // Copy mark type and grade group
        if (null !== $dataModel->getMarkType()) {
            $domainObject->setMarkType($dataModel->getMarkType());
        }
        if ($dataModel->getGradeGroup() instanceof GradeGroup) {
            $domainObject->setGradeGroup($dataModel->getGradeGroup());
        }
    }


    /**
     * @param ProjectModel $project
     * @return null|ISummator
     * @throws UnexpectedValueException
     */
    private function getSummator(ProjectModel $project): ?ISummator
    {
        $summatorString = $project->getSummator();
        if (null === $summatorString) {
            return null;
        }
        switch ($summatorString) {
            case ProjectModel::SUMMATOR_AVERAGE:
                return new Average();
            case ProjectModel::SUMMATOR_WEIGHTED_AVERAGE:
                return new WeightedAverage();
            case ProjectModel::SUMMATOR_MINIMUM:
                return new Minimum();
            case ProjectModel::SUMMATOR_MAXIMUM:
                return new Maximum();
        }
        throw new UnexpectedValueException('$model->getSummator() is not a recognised summator type');
    }
}
