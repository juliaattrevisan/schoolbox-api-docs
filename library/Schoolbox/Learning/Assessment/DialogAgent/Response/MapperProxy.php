<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent\Response;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Mapper as ResponseMapper;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model as DomainObject;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model as DataObject;
use Schoolbox\Mapper;

/**
 * The new (165+) LMS uses IResponses + IFeedback
 * The old (160-) LMS used traditional Mapper Models
 *
 * This class works with the new objects, and
 * wraps a traditional Mapper to interface with the database.
 *
 * @method ResponseMapper getMapper()
 * @method DomainObject createModel()
 */
class MapperProxy extends Mapper\AbstractMapperProxy
{
    /**
     * @var string
     */
    private string $externalColumnName = 'assessment_id';


    /**
     * @var string
     */
    private string $internalColumnName = 'dialog_agent_id';


    /**
     * @var string
     */
    private string $selectString = 'SELECT id FROM dialog_agent_response';


    /**
     * This only works with the Dialog Agent Response mapper
     *
     * @param ResponseMapper $mapper
     */
    public function __construct(ResponseMapper $mapper)
    {
        parent::__construct($mapper);
    }


    /**
     * @return string
     */
    protected function getModel()
    {
        return DomainObject::class;
    }


    /**
     * @return string
     */
    protected function getExternalColumnName()
    {
        return $this->externalColumnName;
    }


    /**
     * @return string
     */
    protected function getInternalColumnName()
    {
        return $this->internalColumnName;
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return $this->selectString;
    }


    /**
     * Transforms the db-friendly data model representations into their domain model representations.
     *
     * Note: for dialog agent responses, data and domain representations are the same.
     *
     * @param Collection|DataObject[] $mapperModels
     * @return GenericCollection|DomainObject[]
     */
    protected function transformModels(Collection $mapperModels)
    {
        return new GenericCollection(DomainObject::class, $mapperModels->getAll());
    }


    /**
     * Transforms the domain model representations into their db-friendly data model representations.
     *
     * Note: for dialog agent responses, data and domain representations are the same.
     *
     * @param Collection|DomainObject[] $domainObjects
     * @return GenericCollection|DataObject[]
     *
     */
    protected function transformDomainObjects(Collection $domainObjects)
    {
        return $this->getMapper()->createCollection($domainObjects->getAll());
    }
}
