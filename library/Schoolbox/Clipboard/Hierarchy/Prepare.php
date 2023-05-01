<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\IStrategy;
use Schoolbox\Fixtures\Season\Mapper;
use Symfony\Component\Lock\LockInterface;

/**
 * Prepare the db by soft-deleting stuff we're about to reimport
 * This should allows you to remove stale data safely since they
 * weren't respawned during import processes
 */
class Prepare implements IStrategy
{
    /**
     * @param LoggerInterface                          $log
     * @param bool                                     $perform
     * @param LockInterface                            $lock
     * @param bool                                     $partial
     * @param Mapper                                   $seasonMapper
     * @param \Schoolbox\Fixtures\Sport\Mapper         $sportMapper
     * @param \Schoolbox\Fixtures\Position\Mapper      $positionMapper
     * @param \Schoolbox\Fixtures\Division\Mapper      $divisionMapper
     * @param \Schoolbox\Fixtures\Team\Mapper          $teamMapper
     * @param \Schoolbox\Fixtures\Team\Player\Mapper   $teamPlayerMapper
     * @param \Schoolbox\Fixtures\Team\Position\Mapper $teamPositionMapper
     * @param \Schoolbox\Fixtures\Team\Coach\Mapper    $teamCoachMapper
     * @param \Schoolbox\Fixtures\Coach\Mapper         $coachMapper
     * @param \Schoolbox\Fixtures\Division\User\Mapper $divisionUserMapper
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private bool $partial,
        private LockInterface $lock,
        private \Schoolbox\Fixtures\Season\Mapper $seasonMapper,
        private \Schoolbox\Fixtures\Sport\Mapper $sportMapper,
        private \Schoolbox\Fixtures\Position\Mapper $positionMapper,
        private \Schoolbox\Fixtures\Division\Mapper $divisionMapper,
        private \Schoolbox\Fixtures\Team\Mapper $teamMapper,
        private \Schoolbox\Fixtures\Team\Player\Mapper $teamPlayerMapper,
        private \Schoolbox\Fixtures\Team\Position\Mapper $teamPositionMapper,
        private \Schoolbox\Fixtures\Team\Coach\Mapper $teamCoachMapper,
        private \Schoolbox\Fixtures\Coach\Mapper $coachMapper,
        private \Schoolbox\Fixtures\Division\User\Mapper $divisionUserMapper
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
                $this->seasonMapper->getDatabase()->query("UPDATE {$this->seasonMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
                $this->sportMapper->getDatabase()->query("UPDATE {$this->sportMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
                $this->positionMapper->getDatabase()->query("UPDATE {$this->positionMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
                $this->divisionMapper->getDatabase()->query("UPDATE {$this->divisionMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
                $this->teamMapper->getDatabase()->query("UPDATE {$this->teamMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
                $this->coachMapper->getDatabase()->query("UPDATE {$this->coachMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");

                /* Hard deletions for relationships
                 */
                $this->teamCoachMapper->getDatabase()->query("DELETE FROM {$this->teamCoachMapper->getTable()}");
                $this->teamPlayerMapper->getDatabase()->query("DELETE FROM {$this->teamPlayerMapper->getTable()}");
                $this->teamPositionMapper->getDatabase()->query("DELETE FROM {$this->teamPositionMapper->getTable()}");
                $this->divisionUserMapper->getDatabase()->query("DELETE FROM {$this->divisionUserMapper->getTable()}");
            }
        }

        $this->log->info('Preparing existing seasons, sports, positions, divisions and teams for create/update');
    }
}
