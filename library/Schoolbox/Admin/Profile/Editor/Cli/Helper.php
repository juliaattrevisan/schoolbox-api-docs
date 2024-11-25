<?php

namespace Schoolbox\Admin\Profile\Editor\Cli;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;

class Helper
{
    /**
     * Constructor.
     *
     * @param IConnector $db
     */
    public function __construct(private IConnector $db) {
    }


    /**
     * Fetch primary ids for roles, returning them in a stack,
     * corresponding to the roletypes specified in $roleType
     *
     * @param array $roleType
     * @return array
     */
    public function fetchRoleIds(array $roleType): array
    {
        $ids = [];

        $placeholders = Builder::getArrayPlaceholders($roleType, 'roletype');
        $params = Builder::getArrayParameters($roleType, 'roletype');

        $statement = $this->db->prepare("
            SELECT id
            FROM groups
            WHERE group_type IN {$placeholders}
        ");

        $statement->execute($params);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $ids[] = $row['id'];
        }

        return $ids;
    }


    /**
     * Fetch primary ids for campuses, returning them in a stack
     *
     * @return array
     */
    public function fetchCampusIds(): array
    {
        $ids = [];

        $statement = $this->db->prepare('SELECT id FROM luCampus');
        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $ids[] = $row['id'];
        }

        return $ids;
    }
}
