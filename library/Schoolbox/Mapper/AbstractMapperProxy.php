<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper;

use Alaress\IDatabase;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;

/**
 * Common logic when we need to wrap a traditional mapper inside an
 * adapter so we can return objects of a different type, or perform
 * some other transformation
 */
abstract class AbstractMapperProxy
{
    /**
     * @var IDatabase
     */
    private $database;

    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->database = \Database::getInstance();
        $this->mapper = $mapper;
    }

    /**
     * Gets a typed Collection of objects that match the specified column values
     *
     * @param array $columnList
     * @param array $options
     * @return GenericCollection
     */
    public function findByColumnList(array $columnList, $options = array())
    {
        if($this->isTranslationRequired($columnList)){
            $columnList = $this->translateColumnList($columnList);
        }

        $mapperModels = $this->getMapper()->findByColumnList($columnList, $options);

        return $this->transformModels($mapperModels);
    }



    /**
     * Determines if the specified column list requires translation before being
     * submitted to the Mapper
     *
     * @param array $columnList
     * @return bool
     */
    protected function isTranslationRequired(array $columnList)
    {
        return array_key_exists($this->getExternalColumnName(), $columnList);
    }

    /**
     * Converts the query into a logically equivalent query that the internal Mapper can understand
     *
     * @param array $columnList
     * @return array
     */
    protected function translateColumnList(array $columnList)
    {
        $internalValues = $this->getInternalValues((array)$columnList[$this->getExternalColumnName()]);
        $columnList[$this->getInternalColumnName()] = $internalValues;
        unset($columnList[$this->getExternalColumnName()]);
        return $columnList;
    }

    /**
     * @return string
     */
    protected abstract function getExternalColumnName();

    /**
     * @return string
     */
    protected abstract function getInternalColumnName();

    /**
     * @param $externalValues
     * @return array
     */
    protected abstract function getInternalValues(array $externalValues);

    /**
     * @param Collection $mapperModels
     * @return GenericCollection
     */
    protected abstract function transformModels(Collection $mapperModels);


    /* Getters / Setters
     */

    /**
     * @return IDatabase|\Database
     */
    protected function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return Mapper
     */
    protected function getMapper()
    {
        return $this->mapper;
    }
}