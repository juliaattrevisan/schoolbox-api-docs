<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\GenericCollection;
use Schoolbox\User;

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
    public function getAudiencingSql(User $user, string $searchPlaceholder = null): string
    {
        return "SELECT 0, 'name todo', 'type todo' from dual WHERE false"; // TODO
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        return ''; // TODO
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
            $house    = $user->getResidentialHouse();
            $values[] = $house ? $house->getId() : null;
        }
        return \array_unique($values);
    }
}
