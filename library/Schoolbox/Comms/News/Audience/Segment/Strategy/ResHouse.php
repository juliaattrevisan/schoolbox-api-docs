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

class ResHouse extends SimpleStrategy
{

    public static function getEntityType(): string
    {
        return 'res_house';
    }

    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // Users have 0:1 residential houses
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
        foreach ($users as $user) {
            if ($user->getResidentialHouse() !== null) {
                $values[$user->getResidentialHouse()->getId()] = $user->getResidentialHouse()->getId();
            }
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

        $pb = $this->newPb($paramPrefix);
        if ($targetNull) {
            $pb->isNull("$userTableAlias.residential_house_id");
        }
        if (count($filtered)) {
            $pb->in("$userTableAlias.residential_house_id", $filtered);
        }
        $pb = $this->newPb($paramPrefix)->any($pb);

        return new UserResolutionSql('', $pb);
    }


    /**
     * @inheritDoc
     */
    public function isUserInAudienceValues(User $user, array $entityValues, string $relationship): ?bool
    {
        switch ($relationship) {
            case Audience\Model::RELATIONSHIP_SELF:
                if ($user->getResidentialHouse() === null) {
                    return self::isValidNullEntityValue() && in_array(null, $entityValues, true);
                }
                return in_array($user->getResidentialHouse()->getId(), $entityValues, false);
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
