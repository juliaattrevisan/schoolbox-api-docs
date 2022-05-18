#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\RoleMapper;

require __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            'help',
            'role:',
            'roletype:',
            'namespace:',
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        $msgNamespaceMapper = \Schoolbox\Realtime\Message\NSMapper::getInstance();
        $nameSpaces         = $msgNamespaceMapper->findAll()->sortBy('slug', 'asc')->getPropertyValues('slug');
        $nameSpaceList      = implode(', ', $nameSpaces);
        /* Help
         */
        if ($this->getOpt('help')) {
            echo <<<HELP

Deletes personal notification settings. This cannot be undone, once performed.

Usage: delete-personalised-settings.php [--role] [--roletype] [--namespace]
        --role: ID of a role to delete settings from
        --roletype: one of staff, parent and student
        --namespace: "all" or one of $nameSpaceList

HELP;

            exit(0);
        }

        if ($this->perform()) {
            $this->getLogger()->info("Deleting from database.");
        } else {
            $this->getLogger()->info("Running in dry-run mode.");
        }

        $msgRerouteMapper = \Schoolbox\Realtime\Message\RerouteMapper::getInstance();
        $msgSourceMapper  = \Schoolbox\Realtime\Message\SourceMapper::getInstance();
        $roleMapper       = RoleMapper::getInstance();

        $roleId = $this->getOpt('role:');
        if ($roleId) {
            try {
                $role  = $roleMapper->findById($roleId);
                $roleIds = [$role->getId()];
            } catch (\Exception $e) {
                $this->getLogger()->error('Unable to find role: ' . $roleId);
                exit(0);
            }
        }

        $roleType = $this->getOpt('roletype:');
        if ($roleType) {
            $roleIds = $roleMapper->findIdsByColumn('group_type', $roleType);
        }

        if (empty($roleIds)) {
            $this->getLogger()->error('Please provide either role or roletype');
            exit(0);
        }

        $nameSpace = $this->getOpt('namespace:');
        if ($nameSpace !== 'all' && !in_array($nameSpace, $nameSpaces)) {
            $this->getLogger()->error('Please provide namespace');
            exit(0);
        }

        $msgRerouteMapper->getDatabase()->beginTransaction();

        if ($nameSpace !== 'all') {
            $targetNameSpace = $msgNamespaceMapper->findOneBySlug($nameSpace);
        }

        $roleIdList = implode(',', $roleIds);
        $params = [];
        $select = "SELECT user_id, msg_source.key, msg_namespace.slug, delivery ";
        $delete = "DELETE msg_reroute ";
        $sql = " FROM msg_reroute
                JOIN msg_source ON msg_source.id = msg_reroute.msg_source_id
                JOIN msg_namespace ON msg_namespace.id = msg_source.namespace
                JOIN user ON user.id = msg_reroute.user_id AND user.deleted_at IS NULL
                JOIN `groups` ON user.group_id = `groups`.id
                WHERE `groups`.id IN ({$roleIdList})
                  AND `groups`.deleted_at IS NULL";
        if (isset($targetNameSpace)) {
            $sql .= " AND msg_namespace.slug = :slug";
            $params = ['slug' => $targetNameSpace];
        }
        $statement = $msgNamespaceMapper->getDatabase()->query($select . $sql, $params);
        foreach ($statement->fetchAll() as $item) {
            $this->getLogger()->debug(sprintf('%s, %s, %s, %s', $item['user_id'], $item['slug'], $item['key'], $item['delivery']));
        }

        $deleteStatement = $msgNamespaceMapper->getDatabase()->prepare($delete . $sql);
        $deleteStatement->execute($params);


        if ($this->perform()) {
            $this->getLogger()->info($deleteStatement->rowCount() . ' entries are deleted');
            $msgRerouteMapper->getDatabase()->commit();
        } else {
            $this->getLogger()->info($deleteStatement->rowCount() . ' entries will be deleted');
            $msgRerouteMapper->getDatabase()->rollback();
        }
    }
};


$kernel = new CliTool\Kernel(
    $app,
    [
        CliTool\Kernel::OPTION_LEVEL  => Monolog\Logger::CRITICAL,
        CliTool\Kernel::OPTION_ERRORS => E_ALL,
    ]
);
$kernel->runApp();
