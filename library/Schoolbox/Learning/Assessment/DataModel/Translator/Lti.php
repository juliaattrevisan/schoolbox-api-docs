<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\LTI\Assessment\DataModel\Model;
use Schoolbox\Learning\Assessment\Lti\Model as DomainObject;

/**
 * Sub-translator for LTI assessment specific fields.
 */
class Lti implements IAssessmentTypeTranslator
{
    /**
     * @inheritDoc
     */
    public function createDataModel(): IAssessableDataModel
    {
        return new Model();
    }

    /**
     * @inheritDoc
     */
    public function createDomainObject(?int $id): IAssessable
    {
        return (new DomainObject())->setId($id);
    }

    /**
     * @inheritDoc
     */
    public function recognisesDomainObject(IAssessable $domainObject): bool
    {
        return $domainObject instanceof DomainObject;
    }

    /**
     * @inheritDoc
     */
    public function recognisesDataModel(IAssessableDataModel $dataModel): bool
    {
        return $dataModel instanceof Model;
    }

    /**
     * @param DomainObject $domainObject
     * @param Model        $dataModel
     */
    public function translateToDataModel(IAssessable $domainObject, IAssessableDataModel $dataModel): void
    {
        $dataModel->setLtiAssessmentUrl($domainObject->getLtiAssessmentUrl());
    }

    /**
     * @param Model        $dataModel
     * @param DomainObject $domainObject
     */
    public function translateToDomainModel(IAssessableDataModel $dataModel, IAssessable $domainObject): void
    {
        $domainObject->setLtiAssessmentUrl($dataModel->getLtiAssessmentUrl());
    }
}
