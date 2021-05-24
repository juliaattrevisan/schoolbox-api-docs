<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use UnderflowException;

/**
 * A simple strategy has extra functionality over a standard strategy
 */
interface ISimpleStrategy extends IStrategy
{

    /**
     * Gets an sql fragment for the ISearch instance.
     *
     * This sql fragment is used as part of a larger UNIONed query with other instances.
     * The result of that mega-query is a set of data containing the following fields:
     *
     * (id, name, entity_type, entity_meta)
     *
     * entity_meta is extra information about the entity
     *
     * ...where "name" is a derived human-readable name for the audiencing segment
     *
     * For example,
     *     - "name" might consist of a Folder Name and its class codes in brackets,
     *     - a Campus name,
     *     - a Year level integer with the prefix "Year"
     *
     * This data is ultimately used to generate a list of (id, name, role type) tuples originally
     * used in selectize's search suggestions list for a given keyword.
     *
     * Returns NULL if no query is available. Callers need to check for this.
     *
     * @param User   $user              Only return results that should be visible to this user
     * @param string $searchPlaceholder If specified, include a WHERE clause to filter by keyword
     * @return string|null
     */
    public function getAudiencingSql(User $user, string $searchPlaceholder = null): ?string;


    /**
     * Returns a data structure suitable for finding users in audiences of this type
     *
     * There are two elements of the return value:
     * * a SQL fragment defining a JOIN to the provided user table alias
     * * predicates to filter results of the userTable and this join. The caller will combine these using INTERSECTION.
     *
     * Note that we don't convert the WHERE to a JOIN, because in some cases we don't want any joins at all.
     *
     * @param string          $userTableAlias The user table's alias that any JOINs must reference
     * @param string          $paramPrefix    Used to ensure PB params are unique result can be combined with others
     * @param string[]|null[] $entityValues
     * @param bool            $excludeMuted   Whether to exclude users who have muted the entity value
     * @return UserResolutionSql
     */
    public function getUserResolutionSql(
        string $userTableAlias,
        string $paramPrefix,
        array $entityValues,
        bool $excludeMuted
    ): UserResolutionSql;


    /**
     * Translate this ITranslate instance from an entity_type to a human-readable version
     *
     * This sql fragment is used as part of a larger UNIONed query with other instances.
     * The result of that mega-query is a set of data containing the following fields:
     *
     * [entity_type, entity_value, name]
     *
     * ...where "name" is a derived human-readable name for the audiencing segment
     *
     * For example,
     *     - "name" might consist of a Folder Name and its class codes in brackets,
     *     - a Campus name,
     *     - a Year level integer with the prefix "Year"
     *
     * This data is ultimately used to generate a list of (id, name) tuples originally
     * used to prepopulate selectize's values from a known set of audiencing segments,
     * but present it in a nice way via a twig fragment
     *
     * @param string[] $entityValues Non null entity values
     * @return TranslateSql
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    public function getTranslateSql(array $entityValues): TranslateSql;

    /**
     * An sql fragment that's used to generate a list of folders which the user has read+ access,
     * in order to identify limitations in a search results set for news articles
     *
     * @param User $user
     * @return string
     */
    public function getSearchSql(User $user): string;


    /**
     * Returns a set of entity_values from a set of users, suitable for insertion into SQL queries
     *
     * If NULL is not a valid value, then it will not be returned in the array, regardless of whether
     * a user's value would otherwise be/include null
     *
     * @param GenericCollection|User[] $users
     * @return string[]|null[]
     */
    public function getValues(GenericCollection $users): array;


    /**
     * Returns whether we can conclude that a user is a match against a set of entity values
     * by examining supplied parameters only. Must not hit the DB. This method exists
     * as a performance short-circuit that is called prior to hitting the DB as a method of last resort.
     *
     * Returns:
     * * TRUE  - if user does match values
     * * FALSE - if user does not match values
     * * NULL  - if we cannot tell whether users matches values and relationship using supplied parameters
     *
     * @param User   $user
     * @param array  $entityValues
     * @param string $relationship
     * @return bool|null
     */
    public function isUserInAudienceValues(User $user, array $entityValues, string $relationship): ?bool;
}
