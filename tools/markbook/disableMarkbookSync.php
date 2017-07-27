<?php
/**
 * Lazy way to enable/disable external markbook sync
 *
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di\Container as Injector;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Assessment\Activity;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\User;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');

// Setup kernel

$kernel = new Schoolbox\Application\Daemon\Kernel('Enable Markbook External Sync');

// Get this kernel started

$kernel->run(function ($di, $loop, $log, $opts) {

    $configMapper = ConfigMapper::getInstance();
    $configMapper->getDatabase()->query("UPDATE config SET value = 0 WHERE name = 'external_markbook'");

});