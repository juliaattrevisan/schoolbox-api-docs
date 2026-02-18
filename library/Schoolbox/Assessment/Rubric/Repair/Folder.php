<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Repair;

use Alaress\Database\Connector\IConnector;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Application\CliTool\Composite;
use Throwable;

/**
 * Composite app for repairing sub-apps.
 */
class Folder extends Composite
{
    /**
     * Constructor
     *
     * This app has a predefined set of sub-apps.
     */
    public function __construct()
    {
        parent::__construct(
            new Duplicate\Merge\Capability(),
            new Duplicate\Merge\Indicator(),
            new Duplicate\Delete\Indicator(),
            new Duplicate\Delete\Capability()
        );
    }


    /**
     * All sub-apps have no-transaction turned on.
     *
     * @param App                $subApp
     * @param array              $appOptions
     * @param ContainerInterface $di
     * @param resource           $outStream
     * @param LoggerInterface    $logger
     * @param bool               $perform
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function setupSubApp(
        App $subApp,
        array $appOptions,
        ContainerInterface $di,
        $outStream,
        LoggerInterface $logger,
        bool $perform
    ): void {
        $appOptions['no-transaction'] = true;
        parent::setupSubApp($subApp, $appOptions, $di, $outStream, $logger, $perform);
    }


    /**
     * All sub-apps are wrapped in a transaction before running.
     *
     * @throws Throwable if any sub-app threw anything
     */
    public function run(): void
    {
        /** @var IConnector $db */
        $db = $this->getDi()->get('databaseInternal');
        $db->beginTransaction();

        try {
            parent::run();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        if ($this->perform()) {
            $db->commit();
        } else {
            $db->rollBack();
        }
    }
}
