<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model as DialogAgentModel;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Mapper as ScenarioMapper;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgentObject;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;

/**
 * Sub-translator for Lesson Plan specific fields.
 */
class DialogAgent implements IAssessmentTypeTranslator
{
    /**
     * @return IAssessableDataModel
     */
    public function createDataModel(): IAssessableDataModel
    {
        return new DialogAgentModel();
    }


    /**
     * @inheritdoc
     */
    public function createDomainObject(?int $id): IAssessable
    {
        return (new DialogAgentObject())->setId($id);
    }


    /**
     * Does this sub-translator recognise this assessment?
     *
     * @param IAssessableDataModel $dataModel
     * @return bool
     */
    public function recognisesDataModel(IAssessableDataModel $dataModel): bool
    {
        return $dataModel instanceof DialogAgentModel;
    }


    /**
     * Does this sub-translator recognise this assessable?
     *
     * @param IAssessable $domainObject
     * @return bool
     */
    public function recognisesDomainObject(IAssessable $domainObject): bool
    {
        return $domainObject instanceof DialogAgentObject;
    }


    /**
     * Copies all attributes from the domainObject to dataModel
     *
     * @param IAssessable          $domainObject  A LessonPlanObject instance
     * @param IAssessableDataModel $dataModel     A LessonPlanModel instance
     * @throws InsufficientHydrationException If domain object is insufficiently hydrated for translation
     * @throws Exception
     */
    public function translateToDataModel(IAssessable $domainObject, IAssessableDataModel $dataModel): void
    {
        $scenarioMapper = ScenarioMapper::getInstance();

        /* Remember the Dialog Agent Scenario that was chosen
         */
        $dataModel->setScenario($domainObject->getScenario());
    }


    /**
     * Copies all attributes from the dataModel to domainObject
     *
     * @param IAssessableDataModel $dataModel
     * @param IAssessable          $domainObject
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    public function translateToDomainModel(IAssessableDataModel $dataModel, IAssessable $domainObject): void
    {
        /* Reuse data models here because they're the same as domain representations
         */
        $scenarioMapper = ScenarioMapper::getInstance();

        /* Dialog Agent Scenario that was associated with this assessment
         */
        $scenario = $scenarioMapper->createModel()
            ->setId($dataModel->getScenario());

        $domainObject->setScenario($scenario);

        $domainObject->setMarkScheme(new None());
    }
}
