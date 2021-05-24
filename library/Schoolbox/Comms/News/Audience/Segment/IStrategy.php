<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\User;

/**
 * A type of audience segmentation, eg 'composite', 'folder', 'role', 'user' etc
 */
interface IStrategy
{

    /**
     * Returns this implementation's entityType
     *
     * This matches the 'entityType' enum in the database
     *
     * @return string
     */
    public static function getEntityType(): string;


    /**
     * Returns whether this segment can logically target null entities. Eg 'users who are not in any classes or groups'
     *
     * This is an inherent property of the entityType's relationship to users
     * Does not consider whether a user is *allowed* to do so.
     *
     * @see IStrategy::canAudienceToEntityValue(User $user, ?string $entityValue)
     *
     * Note that null !== empty. Eg year = ['', null] are different. The first is a non-null entity.
     *
     * @return bool
     */
    public static function isValidNullEntityValue(): bool;


    /**
     * Returns whether the specified user is *permitted* audience to this entity type
     *
     * @param User $user
     * @return bool
     */
    public function canAudienceToEntityType(User $user): bool;


    /**
     * Returns whether the specified user is *permitted* to audience to a value (including null) of this entity type
     *
     * @param User        $user
     * @param null|string $entityValue
     * @return bool
     */
    public function canAudienceToEntityValue(User $user, ?string $entityValue): bool;


    /**
     * Returns the set of valid relationship modifiers for this strategy.
     *
     * Note this is a inherent validity concern, not an authorisation concern.
     *
     * @param string|null $entityMeta    If $entityMeta is provided, it must always return a subset of when $entityMeta
     * is null. Otherwise the validation will break. This is because when the form is posted, the selections are
     * validated prior to save, but the validation step does not provide any meta
     *
     * @return string[]
     */
    public function getValidRelationshipModifiers(string $entityMeta = null): array;


    /**
     * Returns whether the specified user can view an article that is audienced to this value because of the user's relationship
     * to the segment's type and value
     *
     * We may need to consider the modifier. eg [PG:X]^P may imply that parents who have a child in PG:X can read the article.
     *
     * eg, consider an article that is audienced to English3/4 homepage, and a user with READ+ role perms to that homepage.
     *     The user is not in the audience, but should be able to see the article by virtue of being able to see the articles audience
     *
     * @param User        $user
     * @param null|string $entityValue
     * @param string      $modifier
     * @return bool
     */
    public function canViewArticleAudiencedToEntityValue(User $user, ?string $entityValue, string $modifier): bool;


    /**
     * Returns whether the specified user can moderate this entity and value
     *
     * @param User        $user
     * @param null|string $entityValue
     * @return bool
     */
    public function canModerate(User $user, ?string $entityValue): bool;
}
