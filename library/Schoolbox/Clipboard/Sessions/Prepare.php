<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Sessions;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\IStrategy;
use Schoolbox\Fixtures\Matchup\Mapper;
use Symfony\Component\Lock\LockInterface;

/**
 * Prepare the db by soft-deleting stuff we're about to reimport
 * This should allows you to remove stale data safely since they
 * weren't respawned during import processes
 */
class Prepare implements IStrategy
{
    /**
     * @param LoggerInterface                   $log
     * @param bool                              $perform
     * @param bool                              $partial
     * @param LockInterface                     $lock
     * @param Mapper                            $matchupMapper
     * @param \Schoolbox\Fixtures\Result\Mapper $resultMapper
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private bool $partial,
        private LockInterface $lock,
        private \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper,
        private \Schoolbox\Fixtures\Result\Mapper $resultMapper
    ) {
    }


    /**
     * Soft delete relevant data
     *
     * @return void
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->lock->refresh();

            if (!$this->partial) {
                $this->matchupMapper->getDatabase()->query("UPDATE {$this->matchupMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
                $this->resultMapper->getDatabase()->query("UPDATE {$this->resultMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            }
        }

        $this->log->info('Preparing previously-imported Matchups and their results');
    }
}
