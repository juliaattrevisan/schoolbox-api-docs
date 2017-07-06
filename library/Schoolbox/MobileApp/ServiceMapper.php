<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\MobileApp;

use Alaress\Async\Notification\Cloud;
use Alaress\Async\Notification\Cloud\ServiceInterface;
use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage;
use ZendService\Apple\Apns;


/**
 * Class ServiceMapper
 * @package Schoolbox\MobileApp
 * @method ServiceInterface findById($identifierValue)
 * @method Collection|ServiceInterface[] findAll($options = array())
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
        $this->hasModel(ServiceInterface::class);

        $this->hasTable('mobile_app');

        $this->uses('app_id', self::TYPE_STRING, self::COLUMN_IDENTIFIER, 'appIdentifier');
        $this->uses('provider', self::TYPE_STRING, null, 'providerName');
        $this->uses('key', self::TYPE_STRING);
        $this->joins('cert_hash', \Schoolbox\Resource\Storage\Mapper::class, 'hash', null, null, 'certificate', 'cert');
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
     * Indexed by app ID by default
     *
     * @param PDOStatement $statement
     * @return GenericCollection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $appId = $row[$this->getIdentifierColumn()];

            try {
                // index collection by id
                $collection->offsetSet($appId, $this->hydrate($row));

            } catch (Mapper\Exception $e) {
                // this app registration is invalid, just ignore it
            }
        }

        return $collection;
    }


    /**
     * @param array    $row
     * @param string   $alias
     * @param callable $callback
     * @return ServiceInterface
     * @throws Mapper\Exception
     */
    public function hydrate(array $row, $alias = '', $callback = null)
    {
        $rm = RegistryMapper::getInstance();

        $provider = $row[trim("{$alias}.provider", '.')];
        $key      = $row[trim("{$alias}.key", '.')];
        $appId    = $row[trim("{$alias}.app_id", '.')];

        switch ($provider) {
            case Registry::PROVIDER_APPLE:
                /** @var Storage\Model $storageObj */
                $storageObj = $this->hydrateJoin($row, 'cert', 'cert');

                if (!$storageObj) {
                    // invalid PEM reference
                    throw new Mapper\Exception('No PEM certificate given for APNS service.');
                }

                return new Cloud\Apple\APNS($rm, $appId,Apns\Client\Message::PRODUCTION_URI, $this->getPemPath($storageObj), $key);

            case Registry::PROVIDER_GOOGLE:
                return new Cloud\Google\GCM($rm, $appId, $key);
        }

        throw new Mapper\Exception("Invalid provider: must be one of APNS or GCM, '$provider' given.");
    }


    /**
     * Creates a new instance of class with name passed in $this->hasModel().
     *
     * @param int $idValue
     * @throws Mapper\Exception
     * @return object
     */
    public function createModel($idValue = null)
    {
        // one does not simply auto-generate models of this type
        return null;
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
     * Return the path APNS should use for the PEM file held by the given storage object
     *
     * @param Storage\Model $storage
     */
    private function getPemPath(Storage\Model $storage)
    {
        $hash = $storage->getHash();

        // since this is on-construction, check the file in play and see if it needs updating
        if ($this->apnsCertRequiresUpdate($hash)) {
            // copy the cert from storage
            $fp = fopen($this->getTempCertFilename($hash), 'wb');
            $storage->pipe($fp);
            fclose($fp);
        }
    }


    /**
     * Verify whether or not the given PEM hash requires update on-disk
     *
     * @param string $fileHash
     * @return bool
     */
    private function apnsCertRequiresUpdate($fileHash)
    {
        $tempCert = $this->getTempCertFilename($fileHash);

        if (!is_readable($tempCert)) {
            return true;
        }
        if (!filesize($tempCert)) {
            return true;
        }
        if (sha1_file($tempCert) !== $fileHash) {
            return true;
        }

        return false;
    }


    /**
     * Return a consistent temp-file path for the given hash
     *
     * @param string $fileHash
     * @return string
     */
    private function getTempCertFilename($fileHash)
    {
        return sys_get_temp_dir() . "/{$fileHash}.pem";
    }


}