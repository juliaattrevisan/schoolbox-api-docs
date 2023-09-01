#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Copy existing pastoral notification settings to new notifications
 */
$app = new class extends CliTool\App {
    /**
     * Run the cli tool
     */
    public function run(): void
    {
        $query     = "SELECT `id` FROM `groups` WHERE `deleted_at` IS NULL AND `group_type` = 'staff'";
        $statement = $this->getDatabaseInternal()->executeQuery($query);
        $roleIds   = $statement->fetchColumnArray('id');

        // find userAccess without userGroups
        $query       = "SELECT `msg_source`.`id`, `slug`, `key`
                FROM `msg_source`
                JOIN `msg_namespace` ON `msg_namespace`.`id` = `msg_source`.`namespace`
                WHERE `msg_namespace`.`slug` IN ('pastoral', 'pastoral_comment')
                  AND `role_type` = 'staff'
                  AND `msg_source`.`key` IN ('created', 'updated', 'createdRelated', 'updatedRelated')
        ";
        $statement   = $this->getDatabaseInternal()->executeQuery($query);
        $msgSources  = $statement->fetchAll();
        $fromSources = [];
        $toSources   = [];
        foreach ($msgSources as $msgSource) {
            if ($msgSource['key'] === 'createdRelated') {
                $toSources[$msgSource['slug']]['created'] = $msgSource['id'];
            } else {
                if ($msgSource['key'] === 'updatedRelated') {
                    $toSources[$msgSource['slug']]['updated'] = $msgSource['id'];
                } else {
                    $fromSources[] = $msgSource;
                }
            }
        }
        $roleBindings  = \Alaress\Database\Predicate\Builder::getBindings($roleIds, 'role');
        $insertForRole = "
            INSERT INTO msg_route_perm (msg_source_id, role_id, mask, role_default)
            SELECT :to_source_id, role_id, mask, role_default FROM msg_route_perm
            WHERE msg_source_id = :from_source_id AND role_id IN ({$roleBindings->getPlaceholder()})
        ";
        $insertForUser = "
            INSERT INTO msg_reroute (msg_source_id, user_id, delivery)
            SELECT :to_source_id, user_id, delivery FROM msg_reroute
            WHERE msg_source_id = :from_source_id
        ";


        foreach ($fromSources as $fromSource) {
            $this->getDatabaseInternal()->executeQuery(
                $insertForRole,
                array_merge(
                    [
                        'to_source_id'   => $toSources[$fromSource['slug']][$fromSource['key']],
                        'from_source_id' => $fromSource['id'],
                    ],
                    $roleBindings->getParams()
                )
            );
            $this->getDatabaseInternal()->executeQuery(
                $insertForUser,
                [
                    'to_source_id'   => $toSources[$fromSource['slug']][$fromSource['key']],
                    'from_source_id' => $fromSource['id'],
                ],
            );
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

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
