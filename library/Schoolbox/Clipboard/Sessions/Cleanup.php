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
 * Clean out the remnants of our import
 */
class Cleanup implements IStrategy
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
                $this->matchupMapper->getDatabase()->query("UPDATE {$this->matchupMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
                $this->resultMapper->getDatabase()->query("UPDATE {$this->resultMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
            }
        }

        $this->log->info('Soft-deleting matchups that did not appear in the import.');
    }
}
