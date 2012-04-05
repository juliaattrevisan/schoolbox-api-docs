/**
 * This script clears out data for categories that no longer exist
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* delete component instances */
DELETE FROM homepage_comp_location
WHERE cid NOT IN ( SELECT id FROM category );

/* delete access logs */
DELETE FROM homepage_log 
WHERE cid NOT IN ( SELECT id FROM category );

/* delete colour and size configs */
DELETE FROM homepage_config
WHERE cid NOT IN ( SELECT id FROM category );

/* delete cleanURL */
DELETE FROM categoryCode
WHERE categoryID NOT IN ( SELECT id FROM category );

/* delete posted news articles */
DELETE FROM subject_news
WHERE `code` NOT IN ( SELECT id FROM category );

/* delete links */
DELETE FROM link
WHERE category NOT IN ( SELECT id FROM category );

/* delete comments from blogs */
DELETE FROM blogComment
WHERE bid IN (
    SELECT id FROM blog
    WHERE cid NOT IN ( SELECT id FROM category )
);

/* delete blogs */
DELETE FROM blog
WHERE cid NOT IN ( SELECT id FROM category );

/* delete chat polling */
DELETE FROM chat_poll
WHERE category_id NOT IN ( SELECT id FROM category );

/* delete chat logs */
DELETE FROM chat
WHERE category_id NOT IN ( SELECT id FROM category );
