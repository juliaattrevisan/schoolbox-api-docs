<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use Alaress\Database\Predicate\Builder;
use Alaress\IDatabase;
use Schoolbox\Column\IColumnType;
use Schoolbox\Mapper\Exception;

/**
 * Interface IMapper
 *
 * General interface for basic mapping function.
 *
 * @package Schoolbox
 */
interface IMapper
{
    /**
     * Retrieve the singleton instance of this class.
     *
     * @return static
     */
    public static function getInstance();


    /**
     * Returns whether this entity is soft-deletable.
     *
     * This is not necessarily equal to whether the table has a soft-delete column. The entity may take
     * its soft-deletion status from a different table, eg `submission_box` from `assessment`
     *
     * @return bool
     */
    public function isSoftDeletable(): bool;


    /**
     * @param null|int $idValue
     * @return mixed
     * @todo return an IModel interface
     */
    public function createModel($idValue = null);


    /**
     * @return IDatabase
     */
    public function getDatabase();

    /**
     * Retrieves identifier column name.
     *
     * @return string|null
     */
    public function getIdentifierColumn(): ?string;

    /**
     * Create a PredicateBuilder for this Mapper's table.
     *
     * @return Builder
     */
    public function getPredicateBuilder();

    /**
     * @see $this->findAll()
     *
     * @param  array $options
     * @return array
     */
    public function findAllIds(array $options = []);

    /**
     * Override base mapper to always return new collection object
     *
     * @param array $contents
     *
     * @return GenericCollection
     */
    public function createCollection($contents = []);

    /**
     * Gets a single object as a collection, if it isn't one already.
     *
     * Different possible actions based on $object:
     * * instanceof Collection: returns that object
     * * instanceof \ICollection: returns that object converted to collection
     * * array: returns a Collection object wrapping that array
     * * anything else: returns Collection with $object as only item
     *
     * @param mixed $object
     *
     * @return Collection
     */
    public function getModelAsCollection($object);


    /**
     * Retrieves identifier for this mapper (if it exists)
     *
     * @return array|null
     */
    public function getIdentifier(): ?array;


    /**
     * Retrieves identifier property name.
     *
     * @return string|null
     */
    public function getIdentifierProperty(): ?string;


    /**
     * @return string
     */
    public function getTable();

    /**
     * @return MappingCollection
     */
    public function getMap();

    /**
     * Overridden to use getter and hydrator instead of getting property
     * directly.
     *
     * @param object $model
     * @param string $property
     *
     * @return mixed
     */
    public function getModelProperty($model, $property);

    /**
     * Restores one or more models that were soft deleted.
     *
     * @param object|Collection $object Model or collection of models to restore
     *
     * @return bool
     */
    public function restoreSoftDeleted($object);

    /**
     * @see $this->findAll()
     *
     * @param  array $options
     *
     * @return int
     */
    public function countAll($options = array());


    /**
     * @see $this->findByColumn()
     *
     * @param string $columnName
     * @param string $columnValue
     * @param array  $options
     *
     * @return int
     */
    public function countByColumn($columnName, $columnValue, $options = array());

    /**
     * @see $this->findByColumnList()
     *
     * @param  array $columnList
     * @param  array $options
     *
     * @return int
     */
    public function countByColumnList($columnList, $options = array());

    /**
     * @brief Retrieves the entire collection unfiltered
     *
     * @param array $options @see findByColumnList() $options param
     *
     * @return Collection
     */
    public function findAll($options = array());

    /**
     * Retreives a model by id
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     *
     * @param  mixed $identifierValue
     *
     * @return object
     */
    public function findById($identifierValue);


    /**
     * Returns a collection with models filtered by column/property
     *
     * @param String $columnName
     * @param mixed|mixed[] $columnValue
     * @param array  $options @see findByColumnList() $options param
     *
     * @return GenericCollection
     */
    public function findByColumn($columnName, $columnValue, $options = array());

    /**
     * Returns first model whose $columnName = $columnValue.
     *
     * @param  string $columnName
     * @param  mixed  $columnValue
     * @param  array  $options (@see findByColumnList() for list of available options)
     *
     * @return object
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     */
    public function findOneByColumn($columnName, $columnValue, array $options = array());

    /**
     * Returns first model who matches all the values pairs in $columnList.
     *
     * @param  array $columnList
     * @param  array $options    (@see findByColumnList() for list of available options)
     *
     * @return object
     * @throws Exception\Deleted If the model was found but has been deleted
     * @throws Exception         If no instance of model is found
     */
    public function findOneByColumnList($columnList, array $options = array());

    /**
     * @brief Returns a collection with models which match the values pairs in the column list
     *
     * @param array $columnList                  a set of key-value pairs:
     *                                           * key:   the name of the column to find
     *                                           * value: the value (or array of values) to search for
     * @param array $options                     * limit, offset: limit and offset for query
     *                                           * deleted:       whether to retrieve deleted items
     *                                           (defaults to false)
     *                                           * order:         a set of key-value pairs:
     *                                           key: column name, value: ASC or DESC
     *
     * @throws Exception if for some reason the limit or offset parameters
     *                   cannot be created (this should never happen in practise
     *                   as columnList would have to contain a field named
     *                   'limit' or 'offset')
     * @return Collection
     */
    public function findByColumnList($columnList, $options = array());

    /**
     * @brief calls save(), but forces create by nulling the identifier field.
     *
     * @param $object
     *
     * @return array<int>
     */
    public function create($object);

    /**
     * @brief Saves an object to the database. Updates or inserts
     *
     * @param $object
     *
     * @return array<int>|bool
     */
    public function save($object);

    /**
     * Overrides delete() method to call softDelete if a soft delete field was
     * set up, or hardDelete() method if it wasn't.
     *
     * @param $object
     *
     * @return bool
     */
    public function delete($object);


    /**
     * Returns a column's scalar hydrator.
     *
     * ie, Colour, Collection, Date etc will fall back to no hydration, and their raw database value returned.
     *
     * Intentionally throws a TypeError if column does not have a mapping
     *
     * @param string $column
     * @param int    $depth
     * @return IColumnType
     */
    public function getColumnScalarHydrator(string $column, int $depth = 0): IColumnType;
}
