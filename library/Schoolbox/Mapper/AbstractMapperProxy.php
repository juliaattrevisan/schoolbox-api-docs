<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper;

use Alaress\Database\Predicate\Builder;
use Alaress\IDatabase;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
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
            $object = $this->transformModel($e->getDeletedObject());
            if ($object instanceof ISoftDeletable) {
                $e->setDeletedObject($object);
            }
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
        if($this->isColumnTranslationRequired($columnList)){
            $columnList = $this->translateColumnList($columnList);
        }

        $mapperModels = $this->getMapper()->findByColumnList($columnList, $options);

        return $this->transformModels($mapperModels);
    }

    /**
     * Gets a typed Collection of objects that match the specified predicates
     *
     * @param Builder $pb
     * @param array   $options
     * @return GenericCollection
     */
    public function findByPredicates(Builder $pb, $options = array())
    {
        if($this->isPredicatesTranslationRequired($pb)){
            // TODO. For now, you cannot find by Predicates on columns that require translation.
            // TODO. You can avoid hitting this by translating the columns first using translateColumnList()
            throw new \BadMethodCallException('Not yet implemented.');
        }

        $mapperModels = $this->getMapper()->findByPredicates($pb, $options);
        return $this->transformModels($mapperModels);
    }



    /**
     * Saves this object or collection of objects.
     *
     * @param object|Collection $model A model object, or collection thereof
     * @return bool Always true
     * @throws \PDOException if something went wrong in the save process
     */
    public function save($model)
    {
        foreach ($this->getMapper()->getModelAsCollection($model) as $domainObject) {

            $dataModel = $this->transformDomainObject($domainObject);
            $this->getMapper()->save($dataModel);

            // The save action will have set an auto-incrementing ID if $dataModel didn't already exist.
            // Populate the ID of the domain object based on that value.
            if (method_exists($dataModel, 'getId') && method_exists($domainObject, 'setId')) {
                $domainObject->setId($dataModel->getId());
            }

            // Whack in a post-save
            $this->postSave($domainObject, $dataModel);
        }
        return true;
    }


    /**
     * Get the domain model class
     *
     * @return string
     */
    protected abstract function getModel();

    /**
     * Overridable post-save stub.
     *
     * This can be used to sync ID properties from sub-objects from the data
     * model to the domain model.
     *
     * @param $domainObject
     * @param $dataModel
     */
    protected function postSave($domainObject, $dataModel)
    {
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
     * @todo Update signature to accept optional $id and leverage a model cache
     */
    public function createModel()
    {
        $modelClass = $this->getModel();
        return new $modelClass;
    }

    /**
     * Determines if the specified column list requires translation before being
     * submitted to the Mapper
     *
     * @param array $columnList
     * @return bool
     */
    protected function isColumnTranslationRequired(array $columnList)
    {
        return array_key_exists($this->getExternalColumnName(), $columnList);
    }

    /**
     * Determines if the specified predicates requires translation before being
     * submitted to the Mapper
     *
     * @param Builder $pb
     * @return bool
     */
    protected function isPredicatesTranslationRequired(Builder $pb)
    {
        $pattern = $pb->getTable()
            ? "/^`{$pb->getTable()}`.`{$this->getExternalColumnName()}`/"
            : "/^`{$this->getExternalColumnName()}`/";

        foreach($pb->getWhere() as $whereClause)
        {
            if(preg_match($pattern, $whereClause)){
                return true;
            }
        }
        return false;
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
     * Retrieves a single mapping from an external to an internal ID.
     *
     * @param $externalValue
     * @return int
     */
    protected function getInternalValue($externalValue)
    {
        $values = $this->getInternalValues([$externalValue]);
        return isset($values[0]) ? $values[0] : null;
    }

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

        $placeholders = Builder::getArrayPlaceholders($externalValues);

        $query = "
            {$this->getSelectString()}
            WHERE {$this->getDatabase()->quoteIdentifier($this->getExternalColumnName())} IN {$placeholders}
        ";

        $statement = $this->getDatabase()->query($query, Builder::getArrayParameters($externalValues));
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

    /**
     * Converts a single domain model object into a data model.
     *
     * @param object $mapperModel
     * @return object
     */
    public function transformDomainObject($mapperModel)
    {
        return $this->transformDomainObjects(new Collection([$mapperModel]))->first();
    }

    /**
     * @return Builder
     */
    public function getPredicateBuilder()
    {
        return new Builder($this->database, $this->mapper->getTable());
    }

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
