<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\IStrategy;

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
     * @param LoggerInterface $log
     */
    public function __construct(LoggerInterface $log)
    {
        $this->log = $log;

        $this->seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $this->sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $this->positionMapper = \Schoolbox\Fixtures\Position\Mapper::getInstance();
        $this->divisionMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $this->teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
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
     */
    public function import(): void
    {
        $this->seasonMapper->getDatabase()->query("UPDATE {$this->seasonMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
        $this->sportMapper->getDatabase()->query("UPDATE {$this->sportMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
        $this->positionMapper->getDatabase()->query("UPDATE {$this->positionMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
        $this->divisionMapper->getDatabase()->query("UPDATE {$this->divisionMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
        $this->teamMapper->getDatabase()->query("UPDATE {$this->teamMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");

        $this->log->info('Soft-deleting seasons, sports, positions, divisions and teams that did not appear in the import.');
        $this->log->info('Use the orphan remover separately after the second import completes to fully purge stale data.');
    }
}
