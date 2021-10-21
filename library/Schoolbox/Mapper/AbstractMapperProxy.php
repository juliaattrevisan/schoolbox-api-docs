<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper;

/**
 * Proxy class which wraps around a mapper's data retrieval (`findBy`) and
 * manipulation (`save`, `delete`) methods.
 *
 * The most common use case for this is to create a mapper-like interface which
 * works with objects whose logic is closer to the business domain (as opposed
 * to raw mappers, which tend to work with simpler data transfer objects).
 *
 * In this case: `findBy`/`save`/`delete` will return/accept objects of a
 * different class to `Mapper::createModel`.
 */
abstract class AbstractMapperProxy
{
    /**
     * The internal mapper used to fetch data models.
     */
    private Mapper $mapper;

    /**
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Proxy for `$this->mapper->findById()`.
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
     * Proxy for `$this->mapper->findByColumnList()`.
     *
     * If `$columnList` contain `$this->getExternalColumnName()`:
     * * its values will be translated to internal IDs, using a query:
     *   * `$this->getSelectString()` as the SELECT statement which retrieves
     *     internal IDs
     *   * `$this->getExternalColumnName()` as the WHERE part of the statement
     * * the actual mapper call will search on `$this->getInternalColumnName()`
     *
     * @param array $columnList
     * @param array $options
     * @return GenericCollection
     */
    public function findByColumnList(array $columnList, $options = array())
    {
        if ($this->isColumnTranslationRequired($columnList)) {
            $columnList = $this->translateColumnList($columnList);
        }

        $mapperModels = $this->getMapper()->findByColumnList($columnList, $options);

        return $this->transformModels($mapperModels);
    }

    /**
     * Proxy for `$this->mapper->findByPredicates()`.
     *
     * @param Builder $pb
     * @param array   $options
     * @return GenericCollection
     */
    public function findByPredicates(Builder $pb, $options = array())
    {
        if ($this->isPredicatesTranslationRequired($pb)) {
            // TODO. For now, you cannot find by Predicates on columns that require translation.
            // TODO. You can avoid hitting this by translating the columns first using translateColumnList()
            throw new \BadMethodCallException('Not yet implemented.');
        }

        $mapperModels = $this->getMapper()->findByPredicates($pb, $options);
        return $this->transformModels($mapperModels);
    }



    /**
     * Proxy for `$this->mapper->save()`.
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
     * Retrieve the class name of the object which this proxy's
     * `findBy`/`save`/`delete` methods return and accept.
     *
     * @return string
     */
    abstract protected function getModel();

    /**
     * Overridable post-save stub.
     *
     * This can be used to sync ID properties from sub-objects from the data
     * model to the domain model.
     *
     * @param object $domainObject
     * @param object $dataModel
     */
    protected function postSave($domainObject, $dataModel)
    {
    }

    /**
     * Proxy for `$this->mapper->delete()`.
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
        return new $modelClass();
    }

    /**
     * Determines if the specified column list requires translation before being
     * submitted to the internal mapper.
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
     * submitted to the internal mapper.
     *
     * @param Builder $pb
     * @return bool
     */
    protected function isPredicatesTranslationRequired(Builder $pb)
    {
        $pattern = $pb->getTable()
            ? "/^`{$pb->getTable()}`.`{$this->getExternalColumnName()}`/"
            : "/^`{$this->getExternalColumnName()}`/";

        foreach ($pb->getWhere() as $whereClause) {
            if (preg_match($pattern, $whereClause)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Converts the column list into a logical equivalent that the internal
     * mapper can understand.
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
     * Retrieve the externally available column name used when retrieving
     * records.
     *
     * @return string
     */
    abstract protected function getExternalColumnName();

    /**
     * Retrieve the internal column name used when retrieving records.
     *
     * @return string
     */
    abstract protected function getInternalColumnName();

    /**
     * Retrieve the base of the SELECT statement used to find the IDs of the
     * database rows which match the domain model.
     *
     * Used when translating a `findBy` method into something the internal
     * mapper can understand.
     *
     * @return string
     */
    abstract protected function getSelectString();

    /**
     * Retrieves a single mapping from an external to an internal ID.
     *
     * @param int $externalValue
     * @return int
     */
    protected function getInternalValue($externalValue)
    {
        $values = $this->getInternalValues([$externalValue]);
        return $values[0] ?? null;
    }

    /**
     * Retrieves a list of mappings from external to internal IDs.
     *
     * @param array $externalValues
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
            WHERE {$this->mapper->getDatabase()->getQuoter()->quoteIdentifier($this->getExternalColumnName())} IN {$placeholders}
        ";

        $statement = $this->mapper->getDatabase()->query($query, Builder::getArrayParameters($externalValues));
        return $statement->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Transforms data model objects into domain model objects.
     *
     * @param Collection $mapperModels
     * @return GenericCollection
     */
    abstract protected function transformModels(Collection $mapperModels);

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
     * @param Collection $domainObjects
     * @return GenericCollection
     */
    abstract protected function transformDomainObjects(Collection $domainObjects);

    /**
     * Converts a single domain model object into a data model.
     *
     * @param object $domainObject
     * @return object
     */
    protected function transformDomainObject($domainObject)
    {
        return $this->transformDomainObjects(new Collection([$domainObject]))->first();
    }

    /**
     * Builds a predicate builder appropriate for the mapper.
     *
     * @return Builder
     */
    public function getPredicateBuilder()
    {
        // TODO replace with $this->mapper->getBuilder()?
        return new Builder($this->mapper->getDatabase()->getQuoter(), $this->mapper->getTable());
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper
     */
    protected function getMapper()
    {
        return $this->mapper;
    }
}
