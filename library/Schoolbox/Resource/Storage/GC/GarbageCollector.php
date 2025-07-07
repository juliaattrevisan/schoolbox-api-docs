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
     * @param StorageMapper            $mapper        Storage mapper: used for fetching and deleting records.
     * @param LoggerInterface          $log           Logger
     * @param int                      $limit         Maximum number of storage records to process.
     * @param Alaress_Date_Format|null $thresholdDate Do not process any storage records which were last processed more
     *                                                recently than this date.
     * @param bool                     $perform       If true, actually delete storage records and update the last
     *                                                processed time: otherwise, only output what would've been deleted.
     */
    public function __construct(
        private StorageMapper $mapper,
        private LoggerInterface $log,
        private int $limit = 200,
        private ?Alaress_Date_Format $thresholdDate = null,
        private bool $perform = false,
    ) {
    }


    /**
     * Add a check to the garbage collector.
     *
     * @param Check $check
     * @return static
     */
    public function addCheck(Check $check): static
    {
        $this->checks[] = $check;
        return $this;
    }


    /**
     * Returns a list of hashes that are considered stale.
     *
     * @param string|null $hash If set, target this specific hash for cleanup.
     * @return array<string>
     */
    public function getStale(?string $hash = null): array
    {
        // Build the query
        $pb = $this->mapper->getPredicateBuilder();
        if ($hash) {
            $this->log->info(sprintf('Restricting garbage collection to hash: "%s"', $hash));
            $pb = $pb->equals('hash', $hash);
        }
        if ($this->thresholdDate) {
            $thresholdDate = $this->thresholdDate->getDatabase();
            $this->log->info(
                sprintf('Restricting garbage collection to last_gc_at: null or before "%s"', $thresholdDate),
            );
            $pb = $pb->any(
                $this->mapper->getPredicateBuilder()
                    ->isNull('last_gc_at')
                    ->lessThanOrEqualTo('last_gc_at', $thresholdDate)
            );
        }

        // Find the records
        $this->list = $this->mapper->findScalarByPredicates(
            'hash',
            $pb,
            [
                'order'  => [
                    'last_gc_at' => 'asc',
                    'hash'       => 'asc',
                ],
                'limit'  => $this->limit,
                'offset' => 0,
            ]
        );

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
     * Delete all stale hashes.
     *
     * @param string|null $hash If set, target this specific hash for cleanup.
     * @return int
     */
    public function purge(?string $hash = null): int
    {
        $this->log->debug("Starting GC");
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
     * Mark this set of storage hashes as having been processed by the garbage
     * collector, so that `$this->thresholdDate` is respected for future garbage
     * collector runs.
     *
     * @param array<string> $hashes
     */
    private function preserve(array $hashes): void
    {
        if (!$this->perform) {
            return;
        }

        // update all the survivors' last garbage collect dates to current time
        $stmt = $this->mapper->getDatabase()->prepare("
            UPDATE {$this->mapper->getDatabase()->getQuoter()->quoteIdentifier($this->mapper->getTable())}
            SET `last_gc_at` = :now
            WHERE `hash` = :hash
        ");

        $now = Alaress_Date_Format::now()->getDatabase();
        foreach ($hashes as $hash) {
            $stmt->execute(['now' => $now, 'hash' => $hash]);
        }
    }

    /**
     * Completely delete this storage hash, from both the database and from
     * Schoolbox's file system.
     *
     * @param string $hash
     * @throws MapperException
     * @throws FlysystemException if the file could not be deleted from
     *                            Schoolbox's file system.
     */
    private function removeFromStorage(string $hash): void
    {
        if ($this->perform) {
            $this->mapper->actualDelete($this->mapper->findByHash($hash));
            $this->log->debug("Deleted $hash");
        } else {
            $this->log->debug("Would have deleted $hash");
        }
    }
}
