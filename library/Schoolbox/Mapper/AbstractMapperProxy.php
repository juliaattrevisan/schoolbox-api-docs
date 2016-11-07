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
        $this->mapper   = $mapper;
        $this->database = $mapper->getDatabase();
    }

    /**
     * Proxy for $this->mapper->findById().
     *
     * @param int $id
     * @return object
     * @throws Mapper\Exception\Deleted if it was deleted
     */
    public function findById($id)
    {
        try {
            $object = $this->transformModel($this->getMapper()->findById($id));
        } catch (Mapper\Exception\Deleted $e) {
            $e->setDeletedObject($this->transformModel($e->getDeletedObject()));
            throw $e;
        }
        return $object;
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
     * Saves this object or collection of objects.
     *
     * @param object|Collection $model A model object, or collection thereof
     * @return array
     */
    public function save($model)
    {
        return $this->getMapper()->save(
            $this->transformDomainObjects(
                $this->getMapper()->getModelAsCollection($model)
            )
        );
    }

    /**
     * Deletes this object or collection of objects.
     *
     * @param object|Collection $model A model object, or collection thereof
     * @return bool
     */
    public function delete($model)
    {
        return $this->getMapper()->delete(
            $this->transformDomainObjects(
                $this->getMapper()->getModelAsCollection($model)
            )
        );
    }

    /**
     * @return object
     * @throws Exception
     */
    public function createModel()
    {
        return $this->transformModels(new Collection([ $this->getMapper()->createModel() ]))->first();
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
     * @return string
     */
    protected abstract function getSelectString();

    /**
     * @param $externalValues
     * @return array
     */
    protected function getInternalValues(array $externalValues)
    {
        if (empty($externalValues)) {
            return [];
        }
        if (!is_array($externalValues)) {
            $externalValues = [$externalValues];
        }

        $placeholders = Mapper\PredicateBuilder::getArrayPlaceholders($externalValues);

        $query = "
            {$this->getSelectString()}
            WHERE {$this->getDatabase()->quoteIdentifier($this->getExternalColumnName())} IN {$placeholders}
        ";

        $statement = $this->getDatabase()->query($query, Mapper\PredicateBuilder::getArrayParameters($externalValues));
        return $statement->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Transforms data model objects into domain model objects.
     *
     * @param Collection $mapperModels
     * @return GenericCollection
     */
    protected abstract function transformModels(Collection $mapperModels);

    /**
     * Converts a single data model object into a domain model.
     *
     * @param object $model
     * @return object
     */
    protected function transformModel($model)
    {
        return $this->transformModels(new Collection([$model]))->first();
    }

    /**
     * Transforms domain model objects into data model objects.
     *
     * @param Collection $mapperModels
     * @return GenericCollection
     */
    protected abstract function transformDomainObjects(Collection $mapperModels);

    /* Getters / Setters
     */

    /**
     * @return IDatabase
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
