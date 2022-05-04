<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Sessions;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * Clean out the remnants of our import
 */
class Cleanup implements IStrategy
{

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * @var bool
     */
    private bool $perform;


    /**
     * @var LockInterface
     */
    private LockInterface $lock;


    /**
     * @var \Schoolbox\Fixtures\Matchup\Mapper
     */
    private \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper;


    /**
     * @var \Schoolbox\Fixtures\Coach\Mapper
     */
    private \Schoolbox\Fixtures\Coach\Mapper $coachMapper;


    /**
     * @param LoggerInterface                    $log
     * @param bool                               $perform
     * @param LockInterface                      $lock
     * @param \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper
     * @param \Schoolbox\Fixtures\Coach\Mapper   $coachMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        LockInterface $lock,
        \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper,
        \Schoolbox\Fixtures\Coach\Mapper $coachMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->lock = $lock;
        $this->matchupMapper = $matchupMapper;
        $this->coachMapper = $coachMapper;
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

            $this->matchupMapper->getDatabase()->query("UPDATE {$this->matchupMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
            $this->coachMapper->getDatabase()->query("UPDATE {$this->coachMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
        }

        $this->log->info('Soft-deleting matchups that did not appear in the import.');
    }
}
