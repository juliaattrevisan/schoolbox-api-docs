<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\MobileApp;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

/**
 * Class ServiceMapper
 *
 * @package Schoolbox\MobileApp
 * @method Service findById($identifierValue)
 * @method Service findOneByColumn($columnName, $columnValue, array $options = array())
 * @method Service findOneByColumnList($columnList, array $options = [])
 * @method Service createModel($identifierValue = null)
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
        $this->uses('public_configuration', self::TYPE_STRING);
        $this->uses('web_push_public_key', self::TYPE_STRING);
        $this->joins('cert_hash', Storage\Mapper::class, 'hash', null, null, 'certificate', 'cert');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE, 'deletedAt');

        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'blockFirebaseDeletion']);
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

    /**
     * We are wanting to not show Firebase in the list, so hide it in the mapper.
     * @param array $options
     * @return Collection|Service
     */
    public function findAll($options = []): Collection|Service
    {
        $pb = $this->getPredicateBuilder();
        $pb->notLike('provider', Registry::PROVIDER_FIREBASE);
        return $this->findByPredicates($pb, $options);
    }

    /**
     * Pre-soft-delete callback to ensure the Firebase Delivery Provider is not deleted
     *
     * @param Service $service
     * @return bool
     */
    public function blockFirebaseDeletion(Service $service): bool
    {
        if ($service->getProviderName() === Registry::PROVIDER_FIREBASE) {
            throw new SchoolboxException(sprintf('The %s Delivery Provider may not be deleted', Registry::PROVIDER_FIREBASE));
        }
        return true;
    }
}
