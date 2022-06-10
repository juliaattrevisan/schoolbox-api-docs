<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Log;

use PDO;
use PDOStatement;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\Notif\CacheableLoggerInterface;
use Schoolbox\Resource\Storage;

/**
 * Class Mapper
 *
 * @package Schoolbox\Sync\Log
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findById($id)
 * @method Model createModel()
 * @method Collection|Model[] findAll($options = [])
 */
class Mapper extends Schoolbox\Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('ups_log');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('role_type', self::TYPE_STRING);
        $this->uses('dry', self::TYPE_BOOLEAN);
        $this->uses('users_created', self::TYPE_INTEGER);
        $this->uses('users_updated', self::TYPE_INTEGER);
        $this->uses('users_skipped', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);


        $this->joins('hash', Storage\Mapper::class, 'hash', null, null, 'storage');
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        $join = $this->getJoinMapper('hash');
        $t    = $this->getTable();

        $string = "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('hash', 'h')},
                   {$join->getJoinColumnString('allowedFileExtension', 'm')}
            FROM $t
            LEFT JOIN {$this->getJoinSql('hash', $t, 'h')}
            LEFT JOIN {$join->getJoinSql('allowedFileExtension', 'h', 'm', true)}
        ";

        return $string;
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {
            // can't set storage if it doesn't exist
            if (!$r['h.hash']) {
                continue;
            }

            $log = $this->hydrate($r);

            /** @var Storage\Model $store */
            $store = $this->hydrateJoin($r, 'hash', 'h')
                          ->setMimetype(
                              $this->getJoinMapper('hash')
                                   ->hydrateJoin($r, 'allowedFileExtension', 'm')
                          );

//            // ensure path is set
//            if (!$store->getPath()) {
//                $store->setPath($this->getStorageMapper()->getManager()->calcPath($store));
//            }

            $log->setStorage($store);

            $c->add($log);
        }

        return $c;
    }


    /**
     * @return Storage\Mapper
     */
    protected function getStorageMapper()
    {
        return $this->getJoinMapper('hash');
    }


    /**
     * This wraps the Cacheable flush result in a Log and persists it
     *
     * @param CacheableLoggerInterface $cache
     * @param Model                    $log
     */
    public function flush(CacheableLoggerInterface $cache, Model $log): void
    {
        $storage = $cache->flushToStorage();

        // the run didn't actually happen
        if ($storage->getSize() === 0) {
            return;
        }

        $this->save($log->setStorage($storage));
    }
}
