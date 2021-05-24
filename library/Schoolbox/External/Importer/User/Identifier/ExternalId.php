<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User\Identifier;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role as RoleModel;
use Schoolbox\UserMapper;

use function trim;

/**
 * Identifies a user based on the external ID and role type in the row.
 *
 * @package Schoolbox\External\Importer\User\Identifier
 */
class ExternalId implements IdentifierInterface
{
    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var array|RoleModel[]
     */
    protected $roleList;

    /**
     * Constructor
     *
     * @param UserMapper        $userMapper
     * @param array|RoleModel[] $roleList
     */
    public function __construct(UserMapper $userMapper, array $roleList)
    {
        $this->userMapper = $userMapper;
        $this->roleList   = $roleList;
    }

    /**
     * @inheritdoc
     */
    public function accept($row)
    {
        return trim($row['External ID'] ?? '') !== '' && trim($row['Role'] ?? '') !== '';
    }

    /**
     * @inheritdoc
     */
    public function retrieve($row)
    {
        if (array_key_exists($row['Role'], $this->roleList)) {
            $role = $this->roleList[$row['Role']];
        } else {
            throw new \RuntimeException(sprintf('Role "%s" not found', $row['Role']));
        }

        try {
            return $this->userMapper->findOneByColumnList(
                [
                'group_id'   => $role->getId(),
                'synergy_id' => $row['External ID'],
                ],
                ['cache' => false]
            );
        } catch (MapperException $e) {
            return $this->userMapper->createModel();
        }
    }
}
