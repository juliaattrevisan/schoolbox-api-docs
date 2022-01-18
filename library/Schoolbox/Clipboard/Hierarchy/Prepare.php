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
     * @param LoggerInterface                     $log
     * @param \Schoolbox\Fixtures\Season\Mapper   $seasonMapper
     * @param \Schoolbox\Fixtures\Sport\Mapper    $sportMapper
     * @param \Schoolbox\Fixtures\Position\Mapper $positionMapper
     * @param \Schoolbox\Fixtures\Division\Mapper $divisionMapper
     * @param \Schoolbox\Fixtures\Team\Mapper     $teamMapper
     */
    public function __construct(
        LoggerInterface $log,
        \Schoolbox\Fixtures\Season\Mapper $seasonMapper,
        \Schoolbox\Fixtures\Sport\Mapper $sportMapper,
        \Schoolbox\Fixtures\Position\Mapper $positionMapper,
        \Schoolbox\Fixtures\Division\Mapper $divisionMapper,
        \Schoolbox\Fixtures\Team\Mapper $teamMapper
    ) {
        $this->log = $log;
        $this->seasonMapper = $seasonMapper;
        $this->sportMapper = $sportMapper;
        $this->positionMapper = $positionMapper;
        $this->divisionMapper = $divisionMapper;
        $this->teamMapper = $teamMapper;
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
        $this->positionMapper->getDatabase()->query("UPDATE {$this->positionMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
        $this->divisionMapper->getDatabase()->query("UPDATE {$this->divisionMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
        $this->teamMapper->getDatabase()->query("UPDATE {$this->teamMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");

        $this->log->info('Preparing existing seasons, sports, positions, divisions and teams for create/update');
    }
}
