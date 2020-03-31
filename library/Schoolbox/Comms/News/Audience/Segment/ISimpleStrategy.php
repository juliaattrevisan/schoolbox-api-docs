<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\GenericCollection;
use Schoolbox\User;

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
     * @param string $segmentIdsPlaceholder eg ':id_1, :id_2, :id_3';
     * @return string
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string;

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
}
