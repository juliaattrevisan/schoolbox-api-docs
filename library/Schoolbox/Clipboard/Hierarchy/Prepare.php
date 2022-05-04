<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * Prepare the db by soft-deleting stuff we're about to reimport
 * This should allows you to remove stale data safely since they
 * weren't respawned during import processes
 */
class Prepare implements IStrategy
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
     * @var \Schoolbox\Fixtures\Season\Mapper
     */
    private \Schoolbox\Fixtures\Season\Mapper $seasonMapper;


    /**
     * @var \Schoolbox\Fixtures\Sport\Mapper
     */
    private \Schoolbox\Fixtures\Sport\Mapper $sportMapper;


    /**
     * @var \Schoolbox\Fixtures\Position\Mapper
     */
    private \Schoolbox\Fixtures\Position\Mapper $positionMapper;


    /**
     * @var \Schoolbox\Fixtures\Division\Mapper
     */
    private \Schoolbox\Fixtures\Division\Mapper $divisionMapper;


    /**
     * @var \Schoolbox\Fixtures\Team\Mapper
     */
    private \Schoolbox\Fixtures\Team\Mapper $teamMapper;


    /**
     * @var \Schoolbox\Fixtures\Team\Player\Mapper
     */
    private \Schoolbox\Fixtures\Team\Player\Mapper $teamPlayerMapper;


    /**
     * @var \Schoolbox\Fixtures\Team\Position\Mapper
     */
    private \Schoolbox\Fixtures\Team\Position\Mapper $teamPositionMapper;


    /**
     * @var \Schoolbox\Fixtures\Division\User\Mapper
     */
    private \Schoolbox\Fixtures\Division\User\Mapper $divisionUserMapper;


    /**
     * @param LoggerInterface                          $log
     * @param bool                                     $perform
     * @param LockInterface                            $lock
     * @param \Schoolbox\Fixtures\Season\Mapper        $seasonMapper
     * @param \Schoolbox\Fixtures\Sport\Mapper         $sportMapper
     * @param \Schoolbox\Fixtures\Position\Mapper      $positionMapper
     * @param \Schoolbox\Fixtures\Division\Mapper      $divisionMapper
     * @param \Schoolbox\Fixtures\Team\Mapper          $teamMapper
     * @param \Schoolbox\Fixtures\Team\Player\Mapper   $teamPlayerMapper
     * @param \Schoolbox\Fixtures\Team\Position\Mapper $teamPositionMapper
     * @param \Schoolbox\Fixtures\Division\User\Mapper $divisionUserMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        LockInterface $lock,
        \Schoolbox\Fixtures\Season\Mapper $seasonMapper,
        \Schoolbox\Fixtures\Sport\Mapper $sportMapper,
        \Schoolbox\Fixtures\Position\Mapper $positionMapper,
        \Schoolbox\Fixtures\Division\Mapper $divisionMapper,
        \Schoolbox\Fixtures\Team\Mapper $teamMapper,
        \Schoolbox\Fixtures\Team\Player\Mapper $teamPlayerMapper,
        \Schoolbox\Fixtures\Team\Position\Mapper $teamPositionMapper,
        \Schoolbox\Fixtures\Division\User\Mapper $divisionUserMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->lock = $lock;
        $this->seasonMapper = $seasonMapper;
        $this->sportMapper = $sportMapper;
        $this->positionMapper = $positionMapper;
        $this->divisionMapper = $divisionMapper;
        $this->teamMapper = $teamMapper;
        $this->teamPlayerMapper = $teamPlayerMapper;
        $this->teamPositionMapper = $teamPositionMapper;
        $this->divisionUserMapper = $divisionUserMapper;
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

            $this->seasonMapper->getDatabase()->query("UPDATE {$this->seasonMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            $this->sportMapper->getDatabase()->query("UPDATE {$this->sportMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            $this->positionMapper->getDatabase()->query("UPDATE {$this->positionMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            $this->divisionMapper->getDatabase()->query("UPDATE {$this->divisionMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            $this->teamMapper->getDatabase()->query("UPDATE {$this->teamMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");

            /* Hard deletions for relationships
             */
            $this->teamPlayerMapper->getDatabase()->query("DELETE FROM {$this->teamPlayerMapper->getTable()}");
            $this->teamPositionMapper->getDatabase()->query("DELETE FROM {$this->teamPositionMapper->getTable()}");
            $this->divisionUserMapper->getDatabase()->query("DELETE FROM {$this->divisionUserMapper->getTable()}");
        }

        $this->log->info('Preparing existing seasons, sports, positions, divisions and teams for create/update');
    }
}
