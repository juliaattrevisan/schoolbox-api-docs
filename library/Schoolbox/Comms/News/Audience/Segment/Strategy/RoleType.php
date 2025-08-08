<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Segment\TranslateSql;
use Schoolbox\Comms\News\Audience\Segment\UserResolutionSql;
use Schoolbox\GenericCollection;
use Schoolbox\User;

use function count;
use function in_array;

class RoleType extends SimpleStrategy
{
    public static function getEntityType(): string
    {
        return 'role_type';
    }

    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // All users have a role type
        return false;
    }



    /**
     * @inheritdoc
     */
    public function getAudiencingSql(User $user, ?string $searchPlaceholder = null): ?string
    {
        return "SELECT 0, 'name todo', 'type todo', null as entity_meta from dual WHERE false"; // TODO
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(array $entityValues): TranslateSql
    {
        return new TranslateSql('', []); // TODO
    }


    /**
     * @inheritdoc
     */
    public function getSearchSql(User $user): string
    {
        return '';
    }


    /**
     * @inheritdoc
     * @param GenericCollection|User[] $users
     */
    public function getValues(GenericCollection $users): array
    {
        $values = [];
        foreach ($users as $user) {
            $values[$user->getRole()->getType()] = $user->getRole()->getType();
        }
        return $values;
    }


    /** @inheritDoc */
    public function getUserResolutionSql(
        string $userTableAlias,
        string $paramPrefix,
        array $entityValues,
        bool $excludeMuted
    ): UserResolutionSql {
        $targetNull = self::isValidNullEntityValue() && in_array(null, $entityValues, true);
        $filtered   = $this->filterOutNullValues($entityValues);
        if (!$targetNull && !count($filtered)) {
            return $this->buildImpossibleUserResolution($userTableAlias, $paramPrefix);
        }

        $groupAlias = "g_$paramPrefix";
        $join       = "JOIN `groups` $groupAlias ON $userTableAlias.group_id = $groupAlias.id";

        $pb = $this->newPb($paramPrefix)->in("$groupAlias.group_type", $filtered);

        return new UserResolutionSql($join, $pb);
    }

    /**
     * User role types are checked directly.
     *
     * @param User  $user
     * @param array $entityValues
     * @return bool|null
     */
    protected function isSelfInAudienceValues(User $user, array $entityValues): ?bool
    {
        return $user->getRole() ? in_array($user->getRole()->getType(), $entityValues, true) : null;
    }

    /**
     * Parents are not checked for their children's role.
     *
     * @param User  $parent
     * @param array $entityValues
     * @return bool|null
     */
    protected function isUserInAudienceValuesAsParent(User $parent, array $entityValues): ?bool
    {
        return null;
    }

    /**
     * Teachers are not checked for their student's role.
     *
     * @param User  $teacher
     * @param array $entityValues
     * @return bool|null
     */
    protected function isUserInAudienceValuesAsTeacher(User $teacher, array $entityValues): ?bool
    {
        return null;
    }
}
