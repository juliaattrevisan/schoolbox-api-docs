<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Admin;

use Schoolbox\Realtime\Message\RerouteMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Application\Exception\Exception;
use Psr\Log\LoggerInterface;

class Helper
{
    /**
     * @var array
     */
    private $namespaces;

    /**
     * Helper constructor.
     *
     * @param RoleMapper $roleMapper
     * @param RerouteMapper $msgRerouteMapper
     * @param NSMapper $namespaceMapper
     */
    public function __construct(
        private RoleMapper $roleMapper,
        private RerouteMapper $msgRerouteMapper,
        private NSMapper $namespaceMapper
    ) {
        $this->namespaces = $this->namespaceMapper->findAll()->sortBy('slug', 'asc')->getPropertyValues('slug');
    }

    /**
     * Apply default notification settings for a given role id or role type and namespace.
     *
     * @param int|null $roleId
     * @param string|null $roletype
     * @param string $namespace
     * @param bool $isPerform
     * @param LoggerInterface|null $logger
     * @throws Exception
     */
    public function applyDefaultSettings(?int $roleId, ?string $roletype, string $namespace, bool $isPerform = true, ?LoggerInterface $logger = null): void
    {
        // check for a valid role if set
        if ($roleId) {
            try {
                $role  = $this->roleMapper->findById($roleId);
                $roleIds = [$role->getId()];
            } catch (\Exception $e) {
                $logger?->error("Sorry, we are unable to find a valid role for role id: $roleId");
                throw new Exception("Sorry, we are unable to find a valid role for role id: $roleId");
            }
        }

        // check for a valid role type if set
        if ($roletype) {
            $roleIds = $this->roleMapper->findIdsByColumn('group_type', $roletype);
        }

        // if no role type found either, throw an exception
        if (empty($roleIds)) {
            $logger?->error("Please provide either valid role id or roletype");
            throw new Exception("Please provide either valid role id or roletype");
        }

        // check for a valid namespace
        if ($namespace !== "all" && !in_array($namespace, $this->namespaces)) {
            $logger?->error("Please provide a valid namespace. No namespace found for: $namespace");
            throw new Exception("Please provide a valid namespace. No namespace found for: $namespace");
        }

        // retrieve the target namespace to add to the $params array later
        if ($namespace !== "all") {
            $targetNamespace = $this->namespaceMapper->findOneBySlug($namespace);
        }

        if ($isPerform) {
            $logger?->info("Deleting from database.");
        } else {
            $logger?->info("Running in dry-run mode.");
        }

        // begin SQL transaction
        $this->msgRerouteMapper->getDatabase()->beginTransaction();
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
        if (isset($targetNamespace)) {
            $sql .= " AND msg_namespace.slug = :slug";
            $params = ['slug' => $targetNamespace->getSlug()];
        }

        // fetch the records to be deleted and log them
        $statement = $this->namespaceMapper->getDatabase()->query($select . $sql, $params);
        foreach ($statement->fetchAll() as $item) {
            $logger?->debug(sprintf('user id=%s, namespace=%s, type=%s, delivery=%s', $item['user_id'], $item['slug'], $item['key'], $item['delivery']));
        }

        // execute the delete statement
        $deleteStatement = $this->namespaceMapper->getDatabase()->prepare($delete . $sql);
        $deleteStatement->execute($params);

        if ($isPerform) {
            $this->msgRerouteMapper->getDatabase()->commit();
            $logger?->info($deleteStatement->rowCount() . ' entries are deleted');
        } else {
            $this->msgRerouteMapper->getDatabase()->rollBack();
            $logger?->info($deleteStatement->rowCount() . ' entries will be deleted');
        }
    }
}
