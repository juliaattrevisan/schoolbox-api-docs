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

class Helper
{
    /**
     * @var RoleMapper
     */
    private $roleMapper;

    /**
     * @var RerouteMapper
     */
    private $msgRerouteMapper;

    /**
     * @var NSMapper
     */
    private $namespaceMapper;

    /**
     * Helper constructor.
     *
     * @param RoleMapper $roleMapper
     * @param RerouteMapper $msgRerouteMapper
     * @param NSMapper $namespaceMapper
     */
    public function __construct(RoleMapper $roleMapper, RerouteMapper $msgRerouteMapper, NSMapper $namespaceMapper)
    {
        $this->roleMapper = $roleMapper;
        $this->msgRerouteMapper = $msgRerouteMapper;
        $this->namespaceMapper = $namespaceMapper;
    }

    /**
     * Apply default notification settings for a given role type and namespace.
     * TODO - update this helper so tools/notif/delete-personalised-settings.php can also access
     *
     * @param string $roletype
     * @param string $namespace
     * @return void
     */
    public function applyDefaultSettings(string $roletype, string $namespace): void
    {
        if ($roletype) {
            $roleIds = $this->roleMapper->findIdsByColumn('group_type', $roletype);
        }

        if (empty($roleIds)) {
            throw new Exception("No roles found for roletype: $roletype");
            return;
        }

        $this->msgRerouteMapper->getDatabase()->beginTransaction();

        $roleIdList = implode(',', $roleIds);
        $params = [];
        $delete = "DELETE msg_reroute ";
        $sql = " FROM msg_reroute
                JOIN msg_source ON msg_source.id = msg_reroute.msg_source_id
                JOIN msg_namespace ON msg_namespace.id = msg_source.namespace
                JOIN user ON user.id = msg_reroute.user_id AND user.deleted_at IS NULL
                JOIN `groups` ON user.group_id = `groups`.id
                WHERE `groups`.id IN ({$roleIdList})
                AND `groups`.deleted_at IS NULL";
        $sql .= " AND msg_namespace.slug = :slug";
        $targetNameSpace = $this->namespaceMapper->findOneBySlug($namespace);
        $params = ['slug' => $targetNameSpace];

        $deleteStatement = $this->namespaceMapper->getDatabase()->prepare($delete . $sql);
        $deleteStatement->execute($params);
        $this->msgRerouteMapper->getDatabase()->commit();

        /**
         * tools/notif/delete-personalised-settings.php for reference below
         */

        // $msgRerouteMapper = \Schoolbox\Realtime\Message\RerouteMapper::getInstance();
        // $roleMapper       = RoleMapper::getInstance();

        // $roleId = $this->getOpt('role:');
        // if ($roleId) {
        //     try {
        //         $role  = $roleMapper->findById($roleId);
        //         $roleIds = [$role->getId()];
        //     } catch (\Exception $e) {
        //         $this->getLogger()->error('Unable to find role: ' . $roleId);
        //         exit(0);
        //     }
        // }

        // $roleType = $this->getOpt('roletype:');
        // if ($roleType) {
        //     $roleIds = $roleMapper->findIdsByColumn('group_type', $roleType);
        // }

        // if (empty($roleIds)) {
        //     $this->getLogger()->error('Please provide either role or roletype');
        //     exit(0);
        // }

        // $nameSpace = $this->getOpt('namespace:');
        // if ($nameSpace !== 'all' && !in_array($nameSpace, $nameSpaces)) {
        //     $this->getLogger()->error('Please provide namespace');
        //     exit(0);
        // }

        // $msgRerouteMapper->getDatabase()->beginTransaction();

        // if ($nameSpace !== 'all') {
        //     $targetNameSpace = $msgNamespaceMapper->findOneBySlug($nameSpace);
        // }

        // $roleIdList = implode(',', $roleIds);
        // $params = [];
        // $select = "SELECT user_id, msg_source.key, msg_namespace.slug, delivery ";
        // $delete = "DELETE msg_reroute ";
        // $sql = " FROM msg_reroute
        //         JOIN msg_source ON msg_source.id = msg_reroute.msg_source_id
        //         JOIN msg_namespace ON msg_namespace.id = msg_source.namespace
        //         JOIN user ON user.id = msg_reroute.user_id AND user.deleted_at IS NULL
        //         JOIN `groups` ON user.group_id = `groups`.id
        //         WHERE `groups`.id IN ({$roleIdList})
        //           AND `groups`.deleted_at IS NULL";
        // if (isset($targetNameSpace)) {
        //     $sql .= " AND msg_namespace.slug = :slug";
        //     $params = ['slug' => $targetNameSpace];
        // }
        // $statement = $msgNamespaceMapper->getDatabase()->query($select . $sql, $params);
        // foreach ($statement->fetchAll() as $item) {
        //     $this->getLogger()->debug(sprintf('%s, %s, %s, %s', $item['user_id'], $item['slug'], $item['key'], $item['delivery']));
        // }

        // $deleteStatement = $msgNamespaceMapper->getDatabase()->prepare($delete . $sql);
        // $deleteStatement->execute($params);


        // if ($this->perform()) {
        //     $this->getLogger()->info($deleteStatement->rowCount() . ' entries are deleted');
        //     $msgRerouteMapper->getDatabase()->commit();
        // } else {
        //     $this->getLogger()->info($deleteStatement->rowCount() . ' entries will be deleted');
        //     $msgRerouteMapper->getDatabase()->rollback();
        // }

    }

}
