#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Permission\Access\UserAccess;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Inserting missing userGroup
 */
$app = new class extends CliTool\App {
    /**
     * Run the cli tool
     */
    public function run(): void
    {
        // find userAccess without userGroups
        $query = "
         SELECT `sid`
         FROM `userAccess` JOIN `folder` ON `sid` = `folder`.`id` AND `folder`.`deleted_at` IS NULL
         WHERE `sid` NOT IN (SELECT `cid` FROM `userGroups`)
           AND `service` = :homepage GROUP BY `sid`
        ";
        $statement = $this->getDatabaseInternal()->executeQuery($query, ['homepage' => UserAccess::SERVICE_HOMEPAGE]);
        $folderIds = $statement->fetchColumnArray('sid');
        $this->getLogger()->info(sprintf("Found %d userGroups missing", count($folderIds)));

        $userGroupInsertQuery = "INSERT INTO `userGroups` VALUES (:folderId, :groupType, :forceFollow)";
        foreach ($folderIds as $folderId) {
            $this->getLogger()->info("Inserting folder with id:{$folderId} to userGroups");
            $this->getDatabaseInternal()->executeQuery($userGroupInsertQuery, [
                'folderId'    => $folderId,
                'groupType'   => \Schoolbox\Resource\Group::PRIVATE_GROUP,
                'forceFollow' => 0,
            ]);
        }
    }

    /**
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
