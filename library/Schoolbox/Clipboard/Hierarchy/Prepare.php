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
     * @var \Schoolbox\Fixtures\Season\Mapper
     */
    private \Schoolbox\Fixtures\Season\Mapper $seasonMapper;


    /**
     * @var \Schoolbox\Fixtures\Sport\Mapper
     */
    private \Schoolbox\Fixtures\Sport\Mapper $sportMapper;


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
        $this->divisionMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $this->teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
    }


    /**
     * Soft delete relevant data
     *
     * @return void
     */
    public function import(): void
    {
        $this->seasonMapper->getDatabase()->query("UPDATE {$this->seasonMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
        $this->sportMapper->getDatabase()->query("UPDATE {$this->sportMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
        $this->divisionMapper->getDatabase()->query("UPDATE {$this->divisionMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
        $this->teamMapper->getDatabase()->query("UPDATE {$this->teamMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");

        $this->log->info('Preparing existing seasons, sports, divisions and teams for create/update');
    }
}
