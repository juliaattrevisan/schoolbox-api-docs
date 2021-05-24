<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\MobileApp;

use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage;

/**
 * Class ServiceMapper
 *
 * @package Schoolbox\MobileApp
 * @method Service findById($identifierValue)
 * @method Service findOneByColumn($columnName, $columnValue, array $options = array())
 * @method Service findOneByColumnList($columnList, array $options = [])
 * @method Service createModel($identifierValue = null)
 * @method Collection|Service[] findAll($options = array())
 */
class ServiceMapper extends Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        // Note that this is an interface
        $this->hasModel(Service::class);

        $this->hasTable('mobile_app');

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('app_identifier', self::TYPE_STRING);
        $this->uses('provider', self::TYPE_STRING, null, 'providerName');
        $this->uses('key', self::TYPE_STRING);
        $this->joins('cert_hash', Storage\Mapper::class, 'hash', null, null, 'certificate', 'cert');
    }


    /**
     * Return the first part of the string required to select from this mapper
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "SELECT {$this->getColumnString()}, {$this->getJoinColumnString('cert', 'cert')}";
    }


    /**
     * @return string
     * @throws Mapper\DefinitionException
     */
    protected function getFromString()
    {
        // include storage by default
        return "FROM {$this->getTable()} LEFT JOIN {$this->getJoinSql('cert', '', 'cert', true)}";
    }


    /**
     * Do not raise a notification
     *
     * @param object $object
     * @param string $verb
     * @param object $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return false;
    }
}
