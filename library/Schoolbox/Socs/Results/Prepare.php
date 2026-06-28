<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Results;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Result\Mapper as ResultMapper;
use Schoolbox\Socs\Fixtures\Registry;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * SOCS results prepare step.
 *
 * Marks existing SOCS fixture results as stale by setting their updated_at
 * timestamps to a sentinel value. Results written during the current run will
 * have their timestamps refreshed, allowing the cleanup step to soft-delete
 * only SOCS-owned results that did not appear in the latest SOCS feed.
 */
class Prepare implements IStrategy
{
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private ResultMapper $resultMapper
    ) {
    }

    /**
     * Soft-delete preparation for SOCS results.
     *
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     * @throws \PDOException When the underlying database query fails
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->lock->refresh();

            // Mark SOCS-owned results as "stale" until they are re-saved
            // by the current import run.
            $this->resultMapper->getDatabase()->executeQuery(
                "
                    UPDATE {$this->resultMapper->getTable()} r
                    INNER JOIN sportsfixtures_match m ON m.id = r.match_id
                    SET r.updated_at = '0000-00-00 00:00:00'
                    WHERE m.external_id LIKE :external_id
                ",
                ['external_id' => Registry::EXTERNAL_ID_PREFIX . '%']
            );
        }

        $this->log->info('Preparing previously-imported fixtures results for SOCS results import.');
    }
}
