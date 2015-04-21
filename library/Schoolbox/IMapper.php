<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox;

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
     * @param String $columnValue
     * @param array  $options @see findByColumnList() $options param
     *
     * @return Collection
     */
    public function findByColumn($columnName, $columnValue, $options = array());
    /**
     * Returns first model whose $columnName = $columnValue.
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     *
     * @param  String $columnName
     * @param  String $columnValue
     * @param  array  $order Fields to order by (@see findByColumnList() $options param)
     *
     * @return object
     */
    public function findOneByColumn($columnName, $columnValue, $order = array());

    /**
     * Returns first model who matches all the values pairs in $columnList.
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     *
     * @param  array $columnList
     * @param  array $order Fields to order by (@see findByColumnList() $options param)
     *
     * @return object
     */
    public function findOneByColumnList($columnList, $order = array());

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
} 