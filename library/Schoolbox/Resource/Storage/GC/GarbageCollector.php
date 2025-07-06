<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

namespace Schoolbox\Resource\Storage\GC;

use Alaress_Date_Format;
use League\Flysystem\FilesystemException as FlysystemException;
use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Storage\GC\Check\Check;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

/**
 * Garbage collector: uses a set of checks to determine if a storage record is
 * still in use, and if it isn't will attempt to delete it.
 */
class GarbageCollector
{
    /**
     * The list of garbage collection checks to perform.
     *
     * @var array<Check>
     */
    private array $checks = [];

    /**
     * The list of storage hashes to be checked by this garbage collector.
     *
     * @var array<string>
     */
    private array $list;

    /**
     * Constructor
     *
     * @param Check[]                  $checks        A list of checks to perform on storage records: the record must
     *                                                fail all of these checks in order to be deleted.
     * @param StorageMapper            $mapper        Storage mapper: used for fetching and deleting records.
     * @param LoggerInterface          $log           Logger
     * @param int                      $limit         Maximum number of storage records to process.
     * @param Alaress_Date_Format|null $thresholdDate Do not process any storage records which were last processed more
     *                                                recently than this date.
     * @param bool                     $perform       If true, actually delete storage records and update the last
     *                                                processed time: otherwise, only output what would've been deleted.
     */
    public function __construct(
        array $checks,
        private StorageMapper $mapper,
        private LoggerInterface $log,
        private int $limit = 200,
        private ?Alaress_Date_Format $thresholdDate = null,
        private bool $perform = false,
    ) {
        foreach ($checks as $c) {
            $this->addCheck($c);
        }
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
     * @var string $hash       If set, target this specific hash for cleanup
     * @return array
     */
    public function getStale($hash = null)
    {
        $query = 'SELECT `hash` FROM `storage` WHERE';

        if ($hash) {
            $query .= ' `hash` = :hash';
            $statement = $this->mapper->getDatabase()->prepare($query);
            $statement->bindValue(':hash', $hash);
        } else {
            $query .= ' `expired_at` IS NULL ORDER BY `last_accessed` ASC LIMIT 0, :limit';
            $statement = $this->mapper->getDatabase()->prepare($query);
            $statement->bindValue(':limit', $this->limit, \PDO::PARAM_INT);
        }

        $statement->execute();

        $this->list = [];

        // start by populating with all hashes
        while ($hash = $statement->fetchColumn()) {
            $this->list[] = $hash;
        }

        $this->log->info('Starting with ' . count($this->list) . ' files');

        // iterate the checks, and retain the intersection each time
        foreach ($this->checks as $check) {
            $this->log->notice('Applying ' . get_class($check), ['char' => '+']);
            $this->list = $check->getStale($this->list);
            $this->log->info('Retained ' . count($this->list) . ' stale');
        }

        return $this->list;
    }


    /**
     * Delete all stale hashes
     * @var string $hash       If set, target this specific hash for cleanup
     * @return int
     */
    public function purge($hash = null)
    {
        $purge = $this->getStale($hash);
        $this->preserve(array_diff($this->list, $purge));

        if (!count($purge)) {
            $this->log->notice("No files to delete", ['char' => '-']);
            return 0;
        }

        $this->log->error('Deleting ' . count($purge) . ' stale');
        foreach ($purge as $hash) {
            try {
                $this->removeFromStorage($hash);
            } catch (MapperException $e) {
                $this->log->error("Could not delete storage file '$hash' due to a database issue: " . $e->getMessage());
            } catch (FlysystemException $e) {
                $this->log->error("Could not delete storage file '$hash' due to filesystem issue: " . $e->getMessage());
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
     * @throws FlysystemException if the storage file can't be deleted from the
     *                            filesystem.
     */
    protected function removeFromStorage($hash)
    {
        if ($this->perform) {
            $this->mapper->actualDelete($this->mapper->findByHash($hash));
            $this->log->debug("Deleted $hash");
        } else {
            $this->log->debug("Would have deleted $hash");
        }
    }
}
