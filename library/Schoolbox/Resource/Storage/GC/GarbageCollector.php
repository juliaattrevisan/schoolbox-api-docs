<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Storage\GC;

use League\Flysystem\Exception as FlysystemException;
use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Storage\GC\Check\Check;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;


class GarbageCollector 
{

    /** @var Check[] */
    protected $checks = [];

    /** @var StorageMapper */
    protected $mapper;

    /** @var LoggerInterface */
    protected $log;

    /** @var array */
    protected $list;

    /** @var int */
    protected $limit;

    /** @var bool */
    protected $perform;


    /**
     * @param Check[]         $checks
     * @param StorageMapper   $mapper
     * @param LoggerInterface $log
     * @param int             $limit
     * @param bool            $perform
     */
    public function __construct(array $checks, StorageMapper $mapper, LoggerInterface $log, $limit = 200, $perform = false)
    {
        foreach ($checks as $c) {
            $this->addCheck($c);
        }

        $this->mapper  = $mapper;
        $this->log     = $log;
        $this->limit   = $limit;
        $this->perform = $perform;

        $this->log->debug("Starting GC");
    }


    /**
     * @param Check $check
     * @return self
     */
    public function addCheck(Check $check)
    {
        $this->checks[] = $check;
        return $this;
    }


    /**
     * Returns a list of hashes that are considered stale.
     * Main class logic.
     *
     * @return array
     */
    public function getStale()
    {
        $statement  = $this->mapper->getDatabase()->query("SELECT `hash` FROM `storage` WHERE `expired_at` IS NULL ORDER BY `last_accessed` ASC LIMIT 0, $this->limit");
        $this->list = [];

        // start by populating with all hashes
        while ($hash = $statement->fetchColumn()) {
            $this->list[] = $hash;
        }

        $hashes = $this->list;

        $this->log->info('Starting with '.count($this->list).' files');

        // iterate the checks, and retain the intersection each time
        foreach ($this->checks as $check) {
            $this->log->notice('Applying '.get_class($check), ['char' => '+']);
            $hashes = $check->getStale($hashes);
            $this->log->info('Retained '.count($hashes). ' stale');
        }

        return $hashes;
    }


    /**
     * Delete all stale hashes
     * @return int
     */
    public function purge()
    {
        $purge = $this->getStale();

        $this->preserve(array_diff($this->list, $purge));

        if (!count($purge)) {
            $this->log->notice("No files to delete", ['char' => '-']);
            return 0;
        }

        $this->log->error('Deleting '.count($purge).' stale');
        foreach ($purge as $hash) {
            try {
                $this->removeFromStorage($hash);
            } catch (MapperException $e) {
                $this->log->error("Could not delete storage file '$hash' due to a database issue: ".$e->getMessage());
            } catch (FlysystemException $e) {
                $this->log->error("Could not delete storage file '$hash' due to filesystem issue: ".$e->getMessage());
            }
        }

        return count($purge);
    }


    /**
     * @param array $hashes
     */
    protected function preserve(array $hashes)
    {
        if (!$this->perform) {
            return;
        }

        // update all the survivors' last-touch dates to current time
        $stmt = $this->mapper->getDatabase()->prepare("UPDATE `storage` SET `last_accessed` = NOW() WHERE `hash` = ?");

        foreach ($hashes as $hash) {
            $stmt->execute([$hash]);
        }
    }

    /**
     * @param string $hash
     * @throws MapperException
     */
    protected function removeFromStorage($hash)
    {
        if ($this->perform) {
            $this->mapper->delete($this->mapper->findByHash($hash), true);
            $this->log->debug("Deleted $hash");
        } else {
            $this->log->debug("Would have deleted $hash");
        }
    }

}
