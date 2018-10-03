<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\User;

/**
 * A type of audience segmentation, eg 'folder', 'role', 'user' etc
 */
interface IStrategy extends ISearch, ITranslate, IAudience, IModerate
{

    /**
     * Returns this implementation's entityType
     *
     * @return string
     */
    public function getEntityType(): string;


    /**
     * Returns whether the specified user audience to segments of this entity type?
     *
     * Always returns TRUE if the user is a SU or has News Service WRITE+ perm
     *
     * @param User $user
     * @return bool
     */
    public function canBeTargetedByUser(User $user): bool;


    /**
     * Returns whether this segment can target null entities. Eg 'users who are not in any classes or groups'
     *
     * Note that null !== empty. Eg year = ['', null] are different. The first is a non-null entity.
     *
     * @return bool
     */
    public function canTargetNullEntityValue(): bool;


    /**
     * Returns the set of available relationship modifiers for this strategy
     *
     * Note this is distinct from 'can user audience to entity value'.
     * That must be true before this is considered.
     *
     * @see ISearch::getSearchSql()
     *
     * @param User $user
     * @return string[]
     */
    public function getValidRelationshipModifiers(User $user): array;


    /**
     * Returns whether the specified user can view an article that is audienced to this value
     *
     * eg, consider an article that is audienced to English3/4 homepage, and a user with READ+ role perms to that homepage.
     *     The user is not in the audience, but should be able to see the article by virtue of being able to see the articles audience
     *
     * @param User        $user
     * @param null|string $entityValue
     * @return bool
     */
    public function canViewEntityValue(User $user, ?string $entityValue): bool;
}
