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
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgentObject;

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
     * @param IAssessable|DialogAgentObject $domainObject A LessonPlanObject instance
     * @param IAssessableDataModel|DialogAgentModel $dataModel A LessonPlanModel instance
     */
    public function translateToDataModel(IAssessable|DialogAgentObject $domainObject, IAssessableDataModel|DialogAgentModel $dataModel): void
    {
        $dataModel->setScenarioId($domainObject->getScenarioId());
        $dataModel->setLanguage($domainObject->getLanguage());
        $dataModel->setHelpfulness($domainObject->getHelpfulness());
        $dataModel->setOpeningMessage($domainObject->getOpeningMessage());
        $dataModel->setUseDifferentiationProfile($domainObject->getUseDifferentiationProfile());
        $dataModel->setRules($domainObject->getRules());
        $dataModel->setPrompt($domainObject->getPrompt());
        $dataModel->setDuration($domainObject->getDuration());
    }


    /**
     * Copies all attributes from the dataModel to domainObject
     *
     * @param IAssessableDataModel|DialogAgentModel $dataModel
     * @param IAssessable|DialogAgentObject $domainObject
     */
    public function translateToDomainModel(IAssessableDataModel|DialogAgentModel $dataModel, IAssessable|DialogAgentObject $domainObject): void
    {
        $domainObject->setScenarioId($dataModel->getScenarioId());
        $domainObject->setLanguage($dataModel->getLanguage());
        $domainObject->setHelpfulness($dataModel->getHelpfulness());
        $domainObject->setUseDifferentiationProfile($dataModel->getUseDifferentiationProfile());
        $domainObject->setOpeningMessage($dataModel->getOpeningMessage());
        $domainObject->setRules($dataModel->getRules());
        $domainObject->setPrompt($dataModel->getPrompt());
        $domainObject->setDuration($dataModel->getDuration());

        /* TODO This *used* tobe needed at some point, but idk why
         *  maybe can just delete this entirely since they're never marked anyway.
         */
//        $domainObject->setMarkScheme(new None());
    }
}
