<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer\User\Identifier;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role as RoleModel;
use Schoolbox\User as UserModel;
use Schoolbox\UserMapper;

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
        return strlen($row['External ID']) && strlen($row['Role']);
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
            return $this->userMapper->findByExternalId($row['External ID'], $role->getType());
        } catch (MapperException $e) {
            return $this->userMapper->createModel();
        }
    }
}
