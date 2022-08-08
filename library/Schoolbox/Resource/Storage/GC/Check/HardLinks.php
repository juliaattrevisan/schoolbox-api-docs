<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
            "SELECT hash FROM blog_image",

            "SELECT hash FROM eportfolio_component_blog_image",

            "SELECT imagehash FROM eportfolio_coverphoto
             WHERE imagehash IS NOT NULL",

            "SELECT hash FROM dropbox
             WHERE hash IS NOT NULL",

            "SELECT hash FROM file
             WHERE hash IS NOT NULL",

            "SELECT image_hash FROM folder
             WHERE image_hash IS NOT NULL",

            "SELECT hash FROM myFile
             WHERE hash IS NOT NULL",

            "SELECT hash FROM pastoral_attachment",

            "SELECT image_hash FROM resource
             WHERE image_hash IS NOT NULL",

            "SELECT cache_hash FROM socialstream_post
             WHERE cache_hash IS NOT NULL",

            "SELECT hash FROM student_submission_file",

            "SELECT storage_id
             FROM submission_box_file",

            "SELECT hash FROM submission_return_file",

            "SELECT background_image_hash FROM tile
             WHERE background_image_hash IS NOT NULL",

            "SELECT hash FROM ups_log
             WHERE hash IS NOT NULL",

            /* Preserve all plagscan records that have extant student_submission_file records
             */
            "SELECT plagscan_document.`storage_hash`
            FROM plagscan_document
            INNER JOIN student_submission_file ON student_submission_file.`id` = plagscan_document.`student_submission_file_id`
            WHERE storage_hash IS NOT NULL AND student_submission_file.`deleted_at` IS NULL",
        ];

        return sprintf("`hash` NOT IN (%s)", implode(' UNION ', $queries));
    }
}
