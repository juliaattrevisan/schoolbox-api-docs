<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Sessions;

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
     * @var bool
     */
    private bool $perform;


    /**
     * @var \Schoolbox\Fixtures\Matchup\Mapper
     */
    private \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper;


    /**
     * @param LoggerInterface                    $log
     * @param bool                               $perform
     * @param \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->matchupMapper = $matchupMapper;
    }


    /**
     * Soft delete relevant data
     *
     * @return void
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->matchupMapper->getDatabase()->query("UPDATE {$this->matchupMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
        }

        $this->log->info('Preparing previously-imported Matchups');
    }
}
