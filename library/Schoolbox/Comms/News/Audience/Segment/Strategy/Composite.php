<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience;
use Schoolbox\User;
use Schoolbox\Comms\News\Audience\Segment\IStrategy;

class Composite implements IStrategy
{

    /**
     * @inheritdoc
     */
    public static function getEntityType(): string
    {
        return 'composite';
    }


    /**
     * @inheritdoc
     */
    public function getValidRelationshipModifiers(string $entityMeta = null): array
    {
        return [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF];
    }


    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // Composites always have a null entity value
        return true;
    }


    /**
     * @inheritdoc
     */
    public function canAudienceToEntityType(User $user): bool
    {
        // All users can audience to composites
        return true;
    }


    /**
     * @inheritdoc
     */
    public function canAudienceToEntityValue(User $user, ?string $entityValue): bool
    {
        // The value is always null. All users can audience to this.
        return true;
    }


    /**
     * @inheritdoc
     */
    public function canViewArticleAudiencedToEntityValue(User $user, ?string $entityValue, string $modifier): bool
    {
        // Consider a composite segment in isolation (ie ignore the fact it likely has children)
        // Should such an article for readable by a user due to the user's relationship to 'composite'.
        // No, that wouldn't make sense.
        return false;
    }


    /**
     * @inheritdoc
     */
    public function canModerate(User $user, ?string $entityValue): bool
    {
        // No users can inherently prevented from moderating composites per se, although they may not be able
        // to moderate the composite due to its children.
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getSearchSql(User $user): string
    {
        return '';
    }
}
