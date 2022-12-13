<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Translator;

use Aura\Web\Request\Values as RequestValues;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\GenericCollection;
use Schoolbox\Pastoral\Access\Mapper;
use Schoolbox\Pastoral\Audience\Model as PastoralAudience;
use Schoolbox\Pastoral\IPastoralAccess;
use Schoolbox\Pastoral\Type\TypeAbstract;
use Schoolbox\UserMapper;

class VisibilityTranslator
{
    public function __construct(
        private Mapper $pastoralAccessMapper,
        private \Schoolbox\Pastoral\Group\Mapper $pastoralGroupMapper,
        private UserMapper $userMapper,
        private string $class
    ) {
    }

    /**
     * @param RequestValues $post
     * @param TypeAbstract  $type
     * @return GenericCollection
     */
    public function prepareAccesses(RequestValues $post, TypeAbstract $type): GenericCollection
    {
        // Save system role permissions, de-duplicating as we go
        $className   = $this->class;
        $accesses    = new GenericCollection($className);
        $emptyAccess = new $className();
        if ($emptyAccess instanceof PastoralAudience) {
            if ($type instanceof \Schoolbox\Pastoral\Type\Model) {
                $emptyAccess->setType($type);
            } elseif ($type instanceof \Schoolbox\Pastoral\SubType\Model) {
                $emptyAccess->setSubtype($type);
            }
        }

        if (is_array($post->get('role'))) {
            $allowedRoles     = $this->pastoralAccessMapper->determineAllowedRoles();
            $allowedRoleTypes = $this->pastoralAccessMapper->determineAllowedRoleTypes();
            foreach ($post->get('role') as $roleId => $accessLevel) {
                $accessLevel = (int)$accessLevel;
                if (!in_array($accessLevel, $this->pastoralAccessMapper::ACCESSES)) {
                    throw new SchoolboxException(sprintf("Invalid access provided for role %s: %s", $roleId, $accessLevel));
                }
                $access = clone $emptyAccess;
                if (!$access instanceof IPastoralAccess) {
                    throw new \UnexpectedValueException('Only IPastoralAccess is supported');
                }
                $access->setAccess($accessLevel);
                $access->setCommentsAllowed($post->getBool("roleComment{$roleId}", false));

                // Is this a role or a role type?
                if (preg_match('/^role-type-(?P<roleType>.*)$/', (string)$roleId, $matches)) {
                    if (!in_array($matches['roleType'], $allowedRoleTypes)) {
                        throw new SchoolboxException("Invalid role type: {$matches['roleType']}");
                    }
                    $access->setRoleType($matches['roleType']);
                } else {
                    $role = $allowedRoles[$roleId] ?? null;
                    if ($role) {
                        $access->setRole($role);
                    } else {
                        throw new SchoolboxException("Invalid role id: $roleId");
                    }
                }
                if ($post->getInt("roleAccessId{$roleId}")) {
                    $access->setId($post->getInt("roleAccessId{$roleId}"));
                }
                $accesses->add($access);
            }
        }

        // Save pastoral group permissions, de-duplicating as we go
        if (is_array($post->get('group'))) {
            $groups = $this->pastoralGroupMapper->findByColumn('id', array_keys($post->get('group')))->indexBy('id');
            foreach ($post->get('group') as $groupId => $accessLevel) {
                $accessLevel = (int)$accessLevel;
                if (!in_array($accessLevel, $this->pastoralAccessMapper::ACCESSES)) {
                    throw new SchoolboxException(sprintf('Invalid access provided for pastoral group %s: %s', $groupId, $accessLevel));
                }
                if (!isset($groups[$groupId])) {
                    throw new SchoolboxException("Invalid pastoral group id: $groupId");
                }
                $access = clone $emptyAccess;
                $access->setAccess($accessLevel);
                $access->setGroup($groups[$groupId]);
                $access->setCommentsAllowed($post->getBool("groupComment{$groupId}", false));
                if ($post->getInt("groupAccessId{$groupId}")) {
                    $access->setId($post->getInt("groupAccessId{$groupId}"));
                }
                $accesses->add($access);
            }
        }

        // Save individual user permissions, de-duplicating as we go
        if (is_array($post->get('user'))) {
            $users = $this->userMapper->findByColumn('id', array_keys($post->get('user')))->indexBy('id');
            foreach ($post->get('user') as $userId => $accessLevel) {
                $accessLevel = (int)$accessLevel;
                if (!in_array($accessLevel, $this->pastoralAccessMapper::ACCESSES)) {
                    throw new SchoolboxException(sprintf("Invalid access provided for pastoral user %s: %s", $userId, $accessLevel));
                }
                if (!isset($users[$userId])) {
                    throw new SchoolboxException("Invalid user id: $userId");
                }
                $access = clone $emptyAccess;
                $access->setAccess($accessLevel);
                $access->setCommentsAllowed($post->getBool("userComment{$userId}", false));
                $access->setUser($users[$userId]);
                if ($post->getInt("userAccessId{$userId}")) {
                    $access->setId($post->getInt("userAccessId{$userId}"));
                }
                $accesses->add($access);
            }
        }

        return $accesses;
    }
}
