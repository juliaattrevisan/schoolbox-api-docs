<?php
/**
 * Update News items. Copies the created_at datetime to published_at datetime
 * for news_items and subject_news tables.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$newsHandler = new News();

/* Update news_items table
 */
$query = "
UPDATE news_items
SET item_datetime_published = item_datetime_posted
WHERE item_datetime_published IS NULL
OR item_datetime_published = '1970-01-01 00:00:00'
OR item_datetime_published = '0000-00-00 00:00:00'";

$newsHandler->db->query($query);

/* Update subject_news
 */
$query = "
UPDATE subject_news
SET datetime_published = datetime_posted
WHERE datetime_published IS NULL
OR datetime_published = '1970-01-01 00:00:00'
OR datetime_published = '0000-00-00 00:00:00'";

$newsHandler->db->query($query);