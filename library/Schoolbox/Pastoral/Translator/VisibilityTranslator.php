<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Translator;

use Alaress\Web\Request\Values;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\GenericCollection;
use Schoolbox\Pastoral\Access\Mapper;
use Schoolbox\Pastoral\Audience\Model as PastoralAudience;
use Schoolbox\Pastoral\IPastoralAccess;
use Schoolbox\Pastoral\Type\TypeAbstract;
use Schoolbox\Role;
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
     * @param Values       $post
     * @param TypeAbstract $type
     * @return GenericCollection
     */
    public function prepareAccesses(Values $post, TypeAbstract $type): GenericCollection
    {
        // Save system role permissions, de-duplicating as we go
        $className   = $this->class;
        $accesses    = new GenericCollection($className);
        $emptyAccess = new $className();
        if (!$emptyAccess instanceof IPastoralAccess) {
            throw new \UnexpectedValueException('Only IPastoralAccess is supported');
        }
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
                $access = self::getBaseAccess(
                    $emptyAccess,
                    $post->getInt("roleAccessId{$roleId}"),
                    (int)$accessLevel,
                    $post->getBool("roleCampus{$roleId}", false),
                    $post->getBool("roleComment{$roleId}", false)
                );

                // Is this a role or a role type?
                if (preg_match('/^role-type-(?P<roleType>.*)$/', (string)$roleId, $matches)) {
                    $roleType = $matches['roleType'];
                    if (!in_array($roleType, $allowedRoleTypes)) {
                        throw new SchoolboxException("Invalid role type: {$roleType}");
                    }
                    $access->setRoleType($roleType);
                    // match campus check is relevant to staff only
                    if ($roleType !== Role::STAFF) {
                        $access->setMatchCampus(false);
                    }
                } else {
                    /** @var Role|null $role */
                    $role = $allowedRoles[$roleId] ?? null;
                    if ($role) {
                        $access->setRole($role);
                        // match campus check is relevant to staff only
                        if (!$role->isStaff()) {
                            $access->setMatchCampus(false);
                        }
                    } else {
                        throw new SchoolboxException("Invalid role id: $roleId");
                    }
                }
                $accesses->add($access);
            }
        }

        // Save pastoral group permissions, de-duplicating as we go
        if (is_array($post->get('group'))) {
            $groups = $this->pastoralGroupMapper->findByColumn('id', array_keys($post->get('group')))->indexBy('id');
            foreach ($post->get('group') as $groupId => $accessLevel) {
                if (!isset($groups[$groupId])) {
                    throw new SchoolboxException("Invalid pastoral group id: $groupId");
                }
                $access = self::getBaseAccess(
                    $emptyAccess,
                    $post->getInt("groupAccessId{$groupId}"),
                    (int)$accessLevel,
                    $post->getBool("groupCampus{$groupId}", false),
                    $post->getBool("groupComment{$groupId}", false)
                );
                $access->setGroup($groups[$groupId]);
                $accesses->add($access);
            }
        }

        // Save individual user permissions, de-duplicating as we go
        if (is_array($post->get('user'))) {
            $users = $this->userMapper->findByColumn('id', array_keys($post->get('user')))->indexBy('id');
            foreach ($post->get('user') as $userId => $accessLevel) {
                if (!isset($users[$userId])) {
                    throw new SchoolboxException("Invalid user id: $userId");
                }

                /* Nothing ever checked roles for Staff or Parent roletypes,
                 * allowing students to get in here by accident due to api usage.
                 * Surely that's not right...anyway, this now only adds Staff
                 * and Parents, silently discarding any other roletype
                 *
                 * You could throw exceptions but then you have to deal with all the
                 * errors in a set of people, which sounds eminently frustrating
                 * You can't even change the outcome of that because students aren't
                 * meant to be given access anyway.
                 */
                $user = $users[$userId];

                if ($user->getRole()->isStaff() || $user->getRole()->isParent()) {
                    $access = self::getBaseAccess(
                        $emptyAccess,
                        $post->getInt("userAccessId{$userId}"),
                        (int)$accessLevel,
                        false,
                        $post->getBool("userComment{$userId}", false)
                    );
                    $access->setUser($user);
                    $accesses->add($access);
                }
            }
        }

        return $accesses;
    }

    /**
     * @param IPastoralAccess $emptyAccess
     * @param int|null        $id
     * @param int             $accessLevel
     * @param bool            $matchCampus
     * @param bool            $commentAllowed
     * @return IPastoralAccess
     */
    private static function getBaseAccess(IPastoralAccess $emptyAccess, ?int $id, int $accessLevel, bool $matchCampus, bool $commentAllowed): IPastoralAccess
    {
        if (!in_array($accessLevel, Mapper::ACCESSES)) {
            throw new SchoolboxException(sprintf("Invalid access %s provided", $accessLevel));
        }
        $access = clone $emptyAccess;
        $access->setAccess($accessLevel)
            ->setMatchCampus($matchCampus)
            ->setCommentsAllowed($accessLevel === Mapper::ACCESS_DENY ? false : $commentAllowed);
        if ($id) {
            $access->setId($id);
        }
        return $access;
    }
}
