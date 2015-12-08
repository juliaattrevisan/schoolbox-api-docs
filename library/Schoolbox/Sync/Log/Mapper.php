<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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
        $this->hasPrototype(new Model());

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('role_type', self::TYPE_STRING);
        $this->uses('dry', self::TYPE_BOOLEAN);
        $this->uses('users_created', self::TYPE_INTEGER);
        $this->uses('users_updated', self::TYPE_INTEGER);
        $this->uses('users_skipped', self::TYPE_INTEGER);

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

            // ensure path is set
            if (!$store->getPath()) {
                $store->setPath($this->getStorageMapper()->getManager()->calcPath($store));
            }

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
     */
    public function flush(CacheableLoggerInterface $cache)
    {
        $storage = $cache->flushToStorage();

        // the run didn't actually happen
        if ($storage->getSize() == 0) {
            return;
        }

        $logfile = $this->generateModelFromLogfile($storage);

        $this->save($logfile);
    }

    /**
     * @param Storage\Model $file
     * @return Model
     */
    protected function generateModelFromLogfile(Storage\Model $file)
    {
        // default values
        $log = $this->createModel()
                    ->setUsersCreated(0)
                    ->setUsersUpdated(0)
                    ->setUsersSkipped(0)
                    ->setRoleType('unknown')
                    ->setStorage($file)
                    ->setDry(true);


        // this is how we'll check the data
        $checks = [

            // this should be the first line
            'Commencing' => function ($message, $log) {
                /** @var Model $log */
                if (preg_match('`Commencing ([a-z]+) user provision(.*)`', $message, $matches)) {
                    $log->setRoleType($matches[1])
                        ->setDry(strpos($matches[2], 'dry') !== false);
                }

            },

            // this should be the last line
            'SUMMARY:' => function ($message, $log) {
                /** @var Model $log */
                if (preg_match('`(\d+) users created, (\d+) users updated, (\d+) users skipped`', $message, $matches)) {
                    $log->setUsersCreated($matches[1])
                        ->setUsersUpdated($matches[2])
                        ->setUsersSkipped($matches[3]);
                }

            }

        ];

        // probe the file for some particular datums
        $fp = $file->readStream();

        // look for our things to extract
        while ($line = fgets($fp)) {
            foreach ($checks as $needle => $callback) {
                if (strpos($line, $needle)) {
                    $data = json_decode($line, true);
                    call_user_func($callback, $data['message'], $log);
                }
            }
        }

        return $log;
    }



}
