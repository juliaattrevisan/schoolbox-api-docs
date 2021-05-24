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

class Service extends SimpleStrategy
{

    /**
     * @return string
     */
    public static function getEntityType(): string
    {
        return 'service';
    }

    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // A user may have no service permissions
        return false;
//        return true; // TODO Turned off as not-yet implemented in front-end so no need to include in query
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
        $values = [];
        foreach ($this->getUserAccessMapper()->findByColumn('uid', $users->getPropertyValues('id')) as $access) {
            $values[] = "{$access->getService()}.{$access->getServiceIdentifier()}.{$access->getAccessLevel()}";
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

        $uaAlias = "ua_$paramPrefix";

        $pb = $this->newPb($paramPrefix);
        foreach ($filtered as $entityValue) {
            [$service, $sid, $type] = \explode('.', $entityValue);
            $innerPb = $this->newPb($paramPrefix);
            if ('*' !== $service) {
                $innerPb->equals("$uaAlias.service", $service);
            }
            if ('*' !== $sid) {
                $innerPb->equals("$uaAlias.sid", $sid);
            }
            if ('*' !== $type) {
                $innerPb->equals("$uaAlias.type", $type);
            }
            $pb->all($innerPb);
        }
        if ($targetNull) {
            $pb->isNull("$uaAlias.uid");
        }
        $pb = $this->newPb($paramPrefix)->any($pb);

        $joinType = $targetNull ? "LEFT" : "";
        $join     = "$joinType JOIN userAccess $uaAlias ON $userTableAlias.id = $uaAlias.uid";

        return new UserResolutionSql($join, $pb);
    }


    /**
     * @inheritDoc
     */
    public function isUserInAudienceValues(User $user, array $entityValues, string $relationship): ?bool
    {
        switch ($relationship) {
            case Audience\Model::RELATIONSHIP_SELF:
                return null;
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
