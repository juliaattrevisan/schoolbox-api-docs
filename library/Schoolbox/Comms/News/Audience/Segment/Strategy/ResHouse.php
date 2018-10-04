<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience;
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
        return true;
    }



    /**
     * @inheritdoc
     */
    public function getSearchSql(User $user, string $searchPlaceholder = null): string
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
    public static function getValidRelationshipModifiers(): array
    {
        return [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF];
    }
}
