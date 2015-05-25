<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Storage\GC\Check;


class HardLinks extends DbCheck
{
    /**
     * Return the SQL condition that this check uses to regard a file as 'stale'.
     * Do not include the WHERE keyword.
     *
     * @return string
     */
    protected function getStaleCondition()
    {
        // all the places hard links occur
        $queries = [
            "SELECT item_image FROM news_items
             WHERE item_image IS NOT NULL
             AND item_deleted = 0",

            "SELECT imagehash FROM eportfolio_coverphoto
             WHERE imagehash IS NOT NULL
             AND deleted_at IS NULL",

            "SELECT hash FROM dropbox
             WHERE hash IS NOT NULL
             AND deleted_at IS NULL",

            "SELECT hash FROM file
             WHERE hash IS NOT NULL
             AND deleted_at IS NULL",

            "SELECT hash FROM myFile
             WHERE hash IS NOT NULL
             AND deleted_at IS NULL",

            "SELECT image_hash FROM resource
             WHERE image_hash IS NOT NULL
             AND deleted_at IS NULL",

            "SELECT cache_hash FROM socialstream_post
             WHERE cache_hash IS NOT NULL
             AND deleted_at IS NULL",

            "SELECT hash FROM student_submission
             WHERE hash IS NOT NULL",

            "SELECT storage_id
             FROM submission_box_file
             LEFT JOIN submission_box
             ON submission_box_file.box_id = submission_box.id
             WHERE submission_box.id IS NULL
             OR submission_box.deleted IS TRUE",

            "SELECT hash FROM submission_return
             WHERE hash IS NOT NULL
             AND deleted IS FALSE",

            "SELECT background_image_hash FROM tile
             WHERE background_image_hash IS NOT NULL
             AND deleted_at IS NULL",
        ];

        return sprintf("`hash` NOT IN (%s)", implode(' UNION ', $queries));
    }

} 