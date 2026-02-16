<?php

/**
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Psr\Log\LoggerInterface;
use Schoolbox\UserMapper;

class exampleTest implements IStrategy
{
    public function __construct(private LoggerInterface $log, private bool $perform, private UserMapper $userMapper)
    {
    }

    public function process(): void
    {
        $database = $this->userMapper->getDatabase();
        $deleteCount = 0;

        // find how many users exist
        $statement = $database->query('SELECT COUNT(*) FROM user');
        $statement->execute();

        $this->log->info('Found ' . $statement->fetchColumn() . ' users');

        // if perform is true, delete all users
        if ($this->perform) {
            $this->log->info('Deleting all users');
            // delete statement
            $deleteCount++;
        } else {
            $this->log->info('Would have deleted all users');
        }

        // log the number of users deleted
        $this->log->info('Deleted ' . $deleteCount . ' users');
    }
}
