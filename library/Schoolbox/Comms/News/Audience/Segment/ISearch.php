<?php
/**
 * ISearch
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

interface ISearch
{

    /**
     * Gets an sql fragment for the ISearch instance.
     *
     * This sql fragment is used as part of a larger UNIONed query with other instances.
     * The result of that mega-query is a set of data containing the following fields:
     *
     * (id, name, entity_type)
     *
     * ...where "name" is a derived human-readable name for the audiencing segment
     *
     * For example,
     *     - "name" might consist of a Folder Name and its class codes in brackets,
     *     - a Campus name,
     *     - a Year level integer with the prefix "Year"
     *
     * This data is ultimately used to generate a list of (id, name) tuples originally
     * used in selectize's search suggestions list for a given keyword.
     *
     * @param string $searchPlaceholder If specified, include a WHERE clause to filter by keyword
     * @return string
     */
    public function getSearchSql(string $searchPlaceholder = null): string;


    /**
     * Returns the type of entity this search instance is for.
     *
     * Used internally for building query, and also a validation to ensure when combining components for
     * the composite strategy interface that each component is of the same entityType
     *
     * @return string
     */
    public function getEntityType(): string;
}
