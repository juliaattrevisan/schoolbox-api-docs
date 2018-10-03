<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\EntityType\RoleType;

use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\Segment\EntityType\StrategyBase;
use Schoolbox\User;

class Strategy extends StrategyBase
{

    public const ENTITY_TYPE = 'role_type';

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
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
    public function getValidRelationshipModifiers(User $user): array
    {
        return [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF];
    }
}

