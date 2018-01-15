<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Model as TaskObject;


/**
 * Sub-translator for task specific fields.
 */
class Task implements IAssessmentTypeTranslator
{
    private $taskMapper;

    public function __construct()
    {
        $this->taskMapper = TaskMapper::getInstance(); // TODO inject
    }


    /**
     * @return IAssessableDataModel
     */
    public function createDataModel(): IAssessableDataModel
    {
        return $this->taskMapper->createModel(); // TODO consider using ID
    }


    /**
     * @return IAssessable
     */
    public function createDomainObject(): IAssessable
    {
        return new TaskObject(); // TODO consider using ID?
    }


    /**
     * Does this sub-translator recognise this assessable?
     *
     * @param IAssessable $domainObject
     * @return bool
     */
    public function recognisesDomainObject(IAssessable $domainObject): bool
    {
        return $domainObject instanceof TaskObject;
    }


    /**
     * Does this sub-translator recognise this assessment?
     *
     * @param IAssessableDataModel $dataModel
     * @return bool
     */
    public function recognisesDataModel(IAssessableDataModel $dataModel): bool
    {
        return $dataModel instanceof TaskModel;
    }


    /**
     * Copies all attributes from the domainObject to dataModel
     *
     * @param IAssessable|TaskObject          $domainObject
     * @param IAssessableDataModel|TaskModel $dataModel
     * @param int                  $depth
     * @param bool                 $translateProjectContext
     */
    public function translateToDataModel(
        IAssessable $domainObject,
        IAssessableDataModel $dataModel,
        int $depth = 0,
        bool $translateProjectContext = true
    ): void {
        // convert to task's model
        // task currently has its own ID, but that will be handled in the mapper proxy
        $dataModel
            ->setOwner($domainObject->getCreator())
            ->setLink($domainObject->getUrl())
            ->setRequiresConfirmation(!$domainObject->isSelfAssessable());
    }

    /**
     * Copies all attributes from the dataModel to domainObject
     *
     * @param IAssessableDataModel|TaskModel $dataModel
     * @param IAssessable|TaskObject          $domainObject ,
     * @param int                  $depth
     * @param bool                 $translateProjectContext
     */
    public function translateToDomainModel(
        IAssessableDataModel $dataModel,
        IAssessable $domainObject,
        int $depth = 0,
        bool $translateProjectContext = true
    ): void {
        // Task-specific fields
        $domainObject
            ->setMarkType((new MarkType())->setId(MarkType::TYPE_COMPLETION))
            ->setUrl($dataModel->getLink())
            ->setCreator($dataModel->getOwner())
            ->setIsSelfAssessable(!$dataModel->getRequiresConfirmation());
    }
}
