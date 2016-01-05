<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer\User\Identifier;

use Schoolbox\Calendar\Relation\Role;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role as RoleModel;
use Schoolbox\RoleMapper;
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
     * @var RoleMapper
     */
    protected $roleMapper;

    /**
     * Constructor
     *
     * @param UserMapper $userMapper
     * @param RoleMapper $roleMapper
     */
    public function __construct(UserMapper $userMapper, RoleMapper $roleMapper)
    {
        $this->userMapper = $userMapper;
        $this->roleMapper = $roleMapper;
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
        try {
            /** @var RoleModel $role */
            $role = $this->roleMapper->findOneByColumn('name', $row['Role']);
        } catch (MapperException $e) {
            throw new \RuntimeException(sprintf('Role "%s" not found', $row['Role']));
        }
        try {
            return $this->userMapper->findByExternalId($row['External ID'], $role->getType());
        } catch (MapperException $e) {
            return $this->userMapper->createModel();
        }
    }
}
