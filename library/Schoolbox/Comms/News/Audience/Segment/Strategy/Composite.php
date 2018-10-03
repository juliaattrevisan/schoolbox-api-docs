<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience;
use Schoolbox\User;

class Composite
{

    /**
     * Returns the set of available relationship modifiers for this strategy
     *
     * Note this is distinct from 'can user audience to entity value'.
     * That must be true before this is considered.
     *
     * @see  ISearch::getSearchSql()
     *
     * @param User $user
     * @return string[]
     * @todo This should be the implementation of an appropriate interface
     */
    public function getValidRelationshipModifiers(User $user): array
    {
        return [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF];
    }
}
