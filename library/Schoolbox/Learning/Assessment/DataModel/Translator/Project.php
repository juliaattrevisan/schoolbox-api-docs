<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DataModel\Project\Mapper as ProjectMapper;
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

    /** @var ProjectMapper */
    private $projectMapper;

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
        $this->projectMapper  = ProjectMapper::getInstance(); // TODO inject
        $this->markTypeMapper = MarkTypeMapper::getInstance(); // TODO inject
    }


    /**
     * @return IAssessableDataModel
     */
    public function createDataModel(): IAssessableDataModel
    {
        return $this->projectMapper->createModel(); // TODO consider using ID
    }


    /**
     * @return IAssessable
     */
    public function createDomainObject(): IAssessable
    {
        return new ProjectObject();
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
     * @param int                               $depth
     * @param bool                              $translateProjectContext
     * @throws UnexpectedValueException
     * @throws \LogicException
     */
    public function translateToDataModel(
        IAssessable $domainObject,
        IAssessableDataModel $dataModel,
        int $depth = 0,
        bool $translateProjectContext = true
    ): void {
        if(null !== $domainObject->getCreator()){
            $dataModel->setOwner($domainObject->getCreator());
        }

        // Infinite-cycle check: Does the domainObject have children but the dataModel does not?
        // TODO method args should include excluded child identifier
        if ($domainObject->getAssessables()->count() && !$dataModel->getAssessments()->count()) {
            foreach ($domainObject->getAssessables() as $idx => $domainChild) {
                $subModel = $this->baseTranslator->translateToDataModel($domainChild, $depth + 1, false)->getAssessmentModel();
                $subModel->setContextProject($dataModel);
                $dataModel->getAssessments()[$idx] = $subModel;
            }
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
     * @param int                               $depth
     * @param bool                              $translateProjectContext
     * @throws UnexpectedValueException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function translateToDomainModel(
        IAssessableDataModel $dataModel,
        IAssessable $domainObject,
        int $depth = 0,
        bool $translateProjectContext = true
    ): void {
        // Project-specific fields

        // Figure out which summator to make
        $domainObject->setSummator($this->getSummator($dataModel));
        null === $dataModel->getOwner() || $domainObject->setCreator($dataModel->getOwner());

        // Convert sub-assessments

        // Does the data model have children but the domain does not?
        // TODO always translate all but the child specified in method args (if supplied)
        if ($dataModel->getAssessments()->count() && !$domainObject->getAssessables()->count()) {
            foreach ($dataModel->getAssessments() as $idx => $dataChild) {
                $domainChild = $this->baseTranslator->translateToDomainModel($dataChild->getSubObject(), $depth + 1, false);
                $domainChild->setContext($domainObject);
                $domainObject->getAssessables()[$idx] = $domainChild;
            }
        }

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
