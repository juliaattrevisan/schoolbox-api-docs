<?php
/**
 * ITranslate
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

interface ITranslate
{

    /**
     * Returns this implementation's entityType
     *
     * @return string
     */
    public function getEntityType(): string;


    /**
     * Translate this ITranslate instance from an entity_type to a human-readable version
     *
     * This sql fragment is used as part of a larger UNIONed query with other instances.
     * The result of that mega-query is a set of data containing the following fields:
     *
     * (comms_news_audience.*, name)
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
}
