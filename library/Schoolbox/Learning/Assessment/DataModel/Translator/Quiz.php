<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Learning\Quiz\Model as QuizModel;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizObject;

/**
 * Sub-translator for quiz specific fields.
 */
class Quiz implements IAssessmentTypeTranslator
{

    private $quizMapper;


    public function __construct()
    {
        $this->quizMapper = QuizMapper::getInstance(); // TODO inject
    }


    /**
     * @return IAssessableDataModel
     */
    public function createDataModel(): IAssessableDataModel
    {
        return $this->quizMapper->createModel(); // TODO consider using ID
    }


    /**
     * @return IAssessable
     */
    public function createDomainObject(): IAssessable
    {
        return new QuizObject(); // TODO consider using ID?
    }


    /**
     * Does this sub-translator recognise this assessable?
     *
     * @param IAssessable $domainObject
     * @return bool
     */
    public function recognisesDomainObject(IAssessable $domainObject): bool
    {
        return $domainObject instanceof QuizObject;
    }


    /**
     * Does this sub-translator recognise this assessment?
     *
     * @param IAssessableDataModel $dataModel
     * @return bool
     */
    public function recognisesDataModel(IAssessableDataModel $dataModel): bool
    {
        return $dataModel instanceof QuizModel;
    }


    /**
     * Copies all attributes from the domainObject to dataModel
     *
     * @param IAssessable|QuizObject         $domainObject
     * @param IAssessableDataModel|QuizModel $dataModel
     * @param int                            $depth
     * @param bool                           $translateProjectContext
     */
    public function translateToDataModel(
        IAssessable $domainObject,
        IAssessableDataModel $dataModel,
        int $depth = 0,
        bool $translateProjectContext = true
    ): void {
        // convert to quiz's model
        $dataModel
            ->setAvailability(QuizModel::AVAILABILITY_ASSESS)
            ->setTitle($domainObject->getTitle())
            ->setDescription($domainObject->getDescription())
            ->setStart($domainObject->getOpenAt())
            ->setFinish($domainObject->getDueAt())
            ->setWeight($domainObject->getWeight())
            ->setMarkVisibility($domainObject->getMarkVisibility())
            ->setAnonymousResult(false)
            ->setUser($domainObject->getCreator())
            ->setCompletionMessage($domainObject->getCompletionMessage())
            ->setDuration($domainObject->getDuration())
            ->setMaxAttempts($domainObject->getMaxAttempts())
            ->setMarkVisibility($domainObject->getMarkVisibility());
    }


    /**
     * Copies all attributes from the dataModel to domainObject
     *
     * @param IAssessableDataModel|QuizModel $dataModel
     * @param IAssessable|QuizObject         $domainObject ,
     * @param int                            $depth
     * @param bool                           $translateProjectContext
     */
    public function translateToDomainModel(
        IAssessableDataModel $dataModel,
        IAssessable $domainObject,
        int $depth = 0,
        bool $translateProjectContext = true
    ): void {
        // Quiz-specific fields
        $domainObject
            ->setMarkType((new MarkType())->setId(MarkType::TYPE_TOTAL))
            ->setMaxAttempts($dataModel->getMaxAttempts())
            ->setMarkVisibility($dataModel->getMarkVisibility())
            ->setDuration($dataModel->getDuration())
            ->setCompletionMessage($dataModel->getCompletionMessage())
            ->setCreator($dataModel->getUser());
    }
}
