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
            $this->matchupMapper->getDatabase()->query("UPDATE {$this->matchupMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
        }

        $this->log->info('Soft-deleting matchups that did not appear in the import.');
    }
}
