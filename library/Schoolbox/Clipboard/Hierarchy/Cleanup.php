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
 * Clean out the remnants of our import
 */
class Cleanup implements IStrategy
{
    /**
     * @param LoggerInterface                     $log
     * @param bool                                $perform
     * @param bool                                $partial
     * @param LockInterface                       $lock
     * @param Mapper                              $seasonMapper
     * @param \Schoolbox\Fixtures\Sport\Mapper    $sportMapper
     * @param \Schoolbox\Fixtures\Position\Mapper $positionMapper
     * @param \Schoolbox\Fixtures\Division\Mapper $divisionMapper
     * @param \Schoolbox\Fixtures\Team\Mapper     $teamMapper
     * @param \Schoolbox\Fixtures\Coach\Mapper    $coachMapper
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
        private \Schoolbox\Fixtures\Coach\Mapper $coachMapper
    ) {
    }


    /**
     * Soft delete relevant data
     *
     * This is the first of a two part import, so we can't just hard-delete rows
     * out of the db - they will still have foreign key relations and stuff
     *
     * But we also don't want to see stale data in the ui
     *
     * Therefore we can soft delete them where they were marked for deletion
     * and never got updated in the last import, intentionally creating somewhat
     * orphaned data which can explicitly (and completely) remove after the
     * second importer runs
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
                $this->seasonMapper->getDatabase()->query("UPDATE {$this->seasonMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
                $this->sportMapper->getDatabase()->query("UPDATE {$this->sportMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
                $this->positionMapper->getDatabase()->query("UPDATE {$this->positionMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
                $this->divisionMapper->getDatabase()->query("UPDATE {$this->divisionMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
                $this->teamMapper->getDatabase()->query("UPDATE {$this->teamMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
                $this->coachMapper->getDatabase()->query("UPDATE {$this->coachMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
            }
        }

        $this->log->info('Soft-deleting seasons, sports, positions, divisions and teams that did not appear in the import.');
        $this->log->info('Use the orphan remover separately after the second import completes to fully purge stale data.');
    }
}
