<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\Segment\TranslateSql;
use Schoolbox\Comms\News\Audience\Segment\UserResolutionSql;
use Schoolbox\GenericCollection;
use Schoolbox\User;

use function count;
use function in_array;

class Super extends SimpleStrategy
{
    public const ENTITY_VALUE        = '1';  // is SU
    public const ENTITY_VALUE_NOT_SU = '0';  // is not SU

    public static function getEntityType(): string
    {
        return 'super';
    }

    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // Only valid entityValues are 0 or 1
        return false;
    }


    /**
     * @inheritdoc
     */
    public function getAudiencingSql(User $user, string $searchPlaceholder = null): ?string
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
        foreach ($users as $user) {
            if ($user->isSuperuser()) {
                return [self::ENTITY_VALUE];
            }
        }
        return [];
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

        // In theory, targeting:
        // * NULL   => not superuser
        // * 'true' => is superuser
        // If we have both, we have a complete set, and can short-circuit
        if ($targetNull && in_array(self::ENTITY_VALUE, $filtered, true)) {
            return new UserResolutionSql('', $this->newPb($paramPrefix)->isNull("$userTableAlias.deleted_at"));
        }

        $suAlias  = "su_$paramPrefix";
        $joinType = $targetNull ? "LEFT" : '';
        $join     = "$joinType JOIN superusers $suAlias ON $userTableAlias.id = $suAlias.id";

        $pb = $this->newPb($paramPrefix)->isNull("$userTableAlias.deleted_at");
        if ($targetNull) {
            $pb->isNull("$suAlias.id");
        }
        $pb = $this->newPb($paramPrefix)->all($pb);

        return new UserResolutionSql($join, $pb);
    }


    /**
     * @inheritDoc
     */
    public function isUserInAudienceValues(User $user, array $entityValues, string $relationship): ?bool
    {
        switch ($relationship) {
            case Audience\Model::RELATIONSHIP_SELF:
                return
                    ($user->isSuperuser() && in_array(self::ENTITY_VALUE, $entityValues, true)) ||
                    (!$user->isSuperuser() && in_array(self::ENTITY_VALUE_NOT_SU, $entityValues, true));
            case Audience\Model::RELATIONSHIP_PARENTS_OF:
                if (!$user->getRole()->isParent()) {
                    return false;
                }
                return null;
            case Audience\Model::RELATIONSHIP_TEACHERS_OF:
                if (!$user->getRole()->isStaff()) {
                    return false;
                }
                return null;
        }
        return null;
    }
}
