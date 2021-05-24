<?php

use Alaress\IDatabase;
use Psr\Log\LoggerInterface;
use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../../conf/core/require.php';

/* A default datetime value that is easily identifiable
 * Only really corrupt articles should result in having this date at the end of migration
 */
define('DEFAULT_DATE', '1999-01-01 00:00:00');

/**
 * Import all the School News into Comms
 *
 * @param IDatabase       $db
 * @param LoggerInterface $log
 * @return int
 */
function importSchoolNews(IDatabase $db, LoggerInterface $log)
{
    $result = $db->query('
        SELECT * 
        FROM news_items
    ');

    for ($i = 0; $row = $result->fetch(\PDO::FETCH_ASSOC); $i++) {
        $articleId = createSchoolNewsArticle($db, $row, $log);

        /* Figger out all the relations, ram them in accordingly
         */
        if ($articleId) {
            if ($row['item_topic_id']) {
                createSchoolNewsTopics($db, $articleId, (int)$row['item_topic_id'], $log);
            }
            createSchoolNewsUserAudience($db, $row['item_id'], $articleId, $log);
            createSchoolNewsCampusRoleAudience($db, $row['item_id'], $articleId, $log);
        } else {
            $log->warning("Could not create/migrate school news {$row['item_id']} {$row['item_heading']}");
        }
    }

    return $i;
}

/**
 * Create an article record in the db
 * Return its identifier
 *
 * @param IDatabase       $db
 * @param array           $row
 * @param LoggerInterface $log
 * @return string
 */
function createSchoolNewsArticle(IDatabase $db, array $row, LoggerInterface $log)
{

    /* Some articles can exist without certain timestamps because...reasons
     * Use item_datetime_posted as the default datetime value
     * If extant, other dates override the default datetime value
     */

    /* Assume every article had a posted (created) date
     * If not, it's indeterminate
     */
    $createdAt = DEFAULT_DATE;

    if (\strlen($row['item_datetime_posted']) > 0) {
        if ((new \Alaress_Date_Format($row['item_datetime_posted'], \Alaress_Date_Format::DATABASE_FORMAT))->isValid()) {
            $createdAt = $row['item_datetime_posted'];
        }
    }

    /* Some articles were never modified, so item_datetime_modified is null
     * Assume it to be the same as createdAt as per contemporary understanding
     */
    $modifiedAt = $createdAt;

    if (\strlen($row['item_datetime_modified']) > 0) {
        if ((new \Alaress_Date_Format($row['item_datetime_modified'], \Alaress_Date_Format::DATABASE_FORMAT))->isValid()) {
            $modifiedAt = $row['item_datetime_modified'];
        }
    }

    /* If publish date is null, assume it went out immediately
     */
    $publishAt = $createdAt;

    if (\strlen($row['item_datetime_published']) > 0) {
        if ((new \Alaress_Date_Format($row['item_datetime_published'], \Alaress_Date_Format::DATABASE_FORMAT))->isValid()) {
            $publishAt = $row['item_datetime_published'];
        }
    }

    /* If expiry date is null, assume it archived immediately as well
     */
    $expiresAt = $publishAt;

    if (\strlen($row['item_datetime_expires']) > 0) {
        if ((new \Alaress_Date_Format($row['item_datetime_expires'], \Alaress_Date_Format::DATABASE_FORMAT))->isValid()) {
            $expiresAt = $row['item_datetime_expires'];
        }
    }

    /* Deleted boolean equates to last modified
     */
    $deletedAt = null;

    if ($row['item_deleted']) {
        $deletedAt = $modifiedAt;
    }

    /* Now that we've accommodated random data configurations, reassign the repaired version
     */
    $mapping = [
        'id' => $row['item_id'],
        'title' => $row['item_heading'],
        'body' => $row['item_body'] ?: '',
        'blurb' => $row['item_blurb'] ?: '',
        'author_id' => $row['item_owner'],
        'feature_image_hash' => $row['item_image'],
        'submitted_at' => $modifiedAt,
        'accepted_by' => $row['item_owner'],
        'accepted_at' => $modifiedAt,
        'publish_at' => $publishAt,
        'expires_at' => $expiresAt,
        'created_at' => $createdAt,
        'updated_at' => $modifiedAt,
        'deleted_at' => $deletedAt
    ];

    /* If empty, skip adding this thing
     */
    if (!\strlen($row['item_image'])) {
        unset($mapping['feature_image_hash']);
    }

    $keys = implode(', ', array_keys($mapping));

    $params = array_combine(array_map(function ($element) {
        return ":{$element}";
    }, array_keys($mapping)), array_values($mapping));

    $values = implode(', ', array_keys($params));

    $db->prepare("
        INSERT INTO comms_news_article ({$keys}) 
        VALUES ({$values})
    ")->execute($params);

    $lastInsertId = $db->lastInsertId();

    /* Log it
     */
    $log->debug("School News {$row['item_id']} {$row['item_heading']} is now article_id {$lastInsertId}");

    return $lastInsertId;
}

/**
 * Assign topicId to articleId
 *
 * @param IDatabase       $db
 * @param int             $articleId
 * @param int             $topicId
 * @param LoggerInterface $log
 */
function createSchoolNewsTopics(IDatabase $db, int $articleId, int $topicId, LoggerInterface $log)
{
    $query = '
        INSERT INTO comms_news_article_topic (article_id, topic_id) 
        VALUES (:articleId, :topicId)
    ';

    $db->prepare($query)->execute([
        'articleId' => $articleId,
        'topicId' => $topicId
    ]);

    /* Log it
     */
    $log->info("School News article_id {$articleId} assigned topic {$topicId}");
}

/**
 * Find out all the Users for this school news
 *
 * @param IDatabase       $db
 * @param int             $oldId
 * @param int             $articleId
 * @param LoggerInterface $log
 */
function createSchoolNewsUserAudience(IDatabase $db, int $oldId, int $articleId, LoggerInterface $log)
{
    $users = $db->query("
        SELECT userID AS id
        FROM news_items ni
        INNER JOIN newsUser nu ON ni.item_id = nu.newsID
        WHERE ni.item_id = {$oldId}
    ");

    foreach ($users as $user) {
        /* Assign user
         */
        $db->query("
            INSERT INTO `comms_news_audience` (comms_news_article_id, entity_type,  entity_value, relationship)
            VALUES ('{$articleId}', '" . \Schoolbox\Comms\News\Audience\Segment\Strategy\User::getEntityType() . "', '{$user['id']}', '" . Model::RELATIONSHIP_SELF . "')
        ");

        /* Log it
         */
        $log->info("School News article_id {$articleId} assigned User audience for user {$user['id']}");
    }
}

/**
 * Find out all the Roles+Campuses for this school news
 *
 * @param IDatabase       $db
 * @param int             $oldId
 * @param int             $articleId
 * @param LoggerInterface $log
 */
function createSchoolNewsCampusRoleAudience(IDatabase $db, int $oldId, int $articleId, LoggerInterface $log)
{
    $stmt = $db->prepare("SELECT gid FROM newsGroup WHERE id = :old_id");
    $stmt->execute(['old_id' => $oldId]);
    $roleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $db->prepare("SELECT campus FROM newsCampus WHERE id = :old_id");
    $stmt->execute(['old_id' => $oldId]);
    $campusIds = $stmt->fetchAll(PDO::FETCH_COLUMN);


    foreach ($roleIds as $roleId) {
        foreach ($campusIds as $campusId) {
            /* Create composite
             */
            $db->query("
                INSERT INTO `comms_news_audience` (comms_news_article_id, entity_type, relationship)
                VALUES ('{$articleId}', '" . \Schoolbox\Comms\News\Audience\Segment\Strategy\Composite::getEntityType() . "', '" . Model::RELATIONSHIP_SELF . "');
            ");

            $compositeSegmentId = $db->lastInsertId();

            /* Log it
             */
            $log->info("School News article_id {$articleId} assigned Composite audience {$compositeSegmentId}");

            /* Assign campus
             */
            $db->query("
                INSERT INTO `comms_news_audience` (parent_id, entity_type, entity_value, relationship)
                VALUES ('{$compositeSegmentId}', '" . \Schoolbox\Comms\News\Audience\Segment\Strategy\Campus::getEntityType() . "', '{$campusId}', '" . Model::RELATIONSHIP_SELF . "')
            ");

            /* Log it
             */
            $log->info("School News article_id {$articleId} assigned Campus audience {$campusId}");

            /* Assign role
             */
            $db->query("
                INSERT INTO `comms_news_audience` (parent_id, entity_type, entity_value, relationship)
                VALUES ('{$compositeSegmentId}', '" . \Schoolbox\Comms\News\Audience\Segment\Strategy\Role::getEntityType() . "', '{$roleId}', '" . Model::RELATIONSHIP_SELF . "')
            ");

            /* Log it
             */
            $log->info("School News article_id {$articleId} assigned Role audience {$roleId}");
        }
    }
}

/**
 * Import all the homepage news too
 *
 * @param IDatabase       $db
 * @param LoggerInterface $log
 * @return int
 */
function importHomepageNews(IDatabase $db, LoggerInterface $log)
{
    $result = $db->query('
        SELECT * 
        FROM subject_news
    ');

    for ($i = 0; $row = $result->fetch(\PDO::FETCH_ASSOC); $i++) {
        $articleId = createHomepageNewsArticle($db, $row, $log);

        /* Figger out all the relations, ram them in accordingly
         */
        if ($articleId) {
            createHomepageNewsAudience($db, $row['id'], $articleId, $log);
        } else {
            $log->warning("Could not create/migrate homepage news {$row['id']} {$row['heading']}");
        }
    }

    return $i;
}

/**
 * Create an article record in the db
 * Return its identifier
 *
 * @param IDatabase       $db
 * @param array           $row
 * @param LoggerInterface $log
 * @return string
 */
function createHomepageNewsArticle(IDatabase $db, array $row, LoggerInterface $log)
{

    /* Some articles can exist without certain timestamps because...reasons
     * Use datetime_posted as the default datetime value
     * If extant, other dates override the default datetime value
     */

    /* Assume every article had a posted (created) date
     * If not, it's indeterminate
     */
    $createdAt = DEFAULT_DATE;

    if (\strlen($row['datetime_posted']) > 0) {
        if ((new \Alaress_Date_Format($row['datetime_posted'], \Alaress_Date_Format::DATABASE_FORMAT))->isValid()) {
            $createdAt = $row['datetime_posted'];
        }
    }

    /* There isn't a modifiedAt equivalent for homepage news
     */
    $modifiedAt = $createdAt;

    /* If publish date is null, assume it went out immediately
     */
    $publishAt = $createdAt;

    if (\strlen($row['datetime_published']) > 0) {
        if ((new \Alaress_Date_Format($row['datetime_published'], \Alaress_Date_Format::DATABASE_FORMAT))->isValid()) {
            $publishAt = $row['datetime_published'];
        }
    }

    /* If expiry date is null, assume it archived immediately as well
     */
    $expiresAt = $publishAt;

    if (\strlen($row['datetime_expires']) > 0) {
        if ((new \Alaress_Date_Format($row['datetime_expires'], \Alaress_Date_Format::DATABASE_FORMAT))->isValid()) {
            $expiresAt = $row['datetime_expires'];
        }
    }

    /* Deleted boolean equates to last modified
     */
    $deletedAt = null;

    if ($row['deleted']) {
        $deletedAt = $modifiedAt;
    }

    $mapping = [
        'title' => $row['heading'],
        'body' => $row['body'] ?: '',
        'blurb' => '',
        'author_id' => $row['owner'],
        'submitted_at' => $createdAt,
        'accepted_by' => $row['owner'],
        'accepted_at' => $createdAt,
        'publish_at' => $publishAt,
        'expires_at' => $expiresAt,
        'created_at' => $createdAt,
        'updated_at' => $modifiedAt,
        'deleted_at' => $deletedAt
    ];

    $keys = implode(', ', array_keys($mapping));

    $params = array_combine(array_map(function ($element) {
        return ":{$element}";
    }, array_keys($mapping)), array_values($mapping));

    $values = implode(', ', array_keys($params));

    $query = "
        INSERT INTO comms_news_article ({$keys}) 
        VALUES ({$values})
    ";

    $db->prepare($query)->execute($params);

    $lastInsertId = $db->lastInsertId();

    /* Log it
     */
    $log->debug("Homepage News {$row['id']} {$row['heading']} is now article_id {$lastInsertId}");

    $query = "UPDATE subject_news SET migration_id = :migrate_id WHERE id = :article_id";
    $db->prepare($query)->execute([':migrate_id' => $lastInsertId,':article_id' => $row['id']]);
    return $lastInsertId;
}

/**
 * Find out all the Folders for this homepage news
 *
 * @param IDatabase       $db
 * @param int             $oldId
 * @param int             $articleId
 * @param LoggerInterface $log
 */
function createHomepageNewsAudience(IDatabase $db, int $oldId, int $articleId, LoggerInterface $log)
{
    $folders = $db->query("
        SELECT code AS id
        FROM subject_news sn
        WHERE sn.id = {$oldId}
    ");

    /* Assign folder
     */
    foreach ($folders as $folder) {
        $db->query("
            INSERT INTO `comms_news_audience` (comms_news_article_id, entity_type,  entity_value, relationship)
            VALUES ('{$articleId}', '" . \Schoolbox\Comms\News\Audience\Segment\Strategy\Folder::getEntityType() . "', '{$folder['id']}', '" . Model::RELATIONSHIP_SELF . "')
        ");

        /* Log it
         */
        $log->info("Homepage News article_id {$articleId} assigned Folder audience {$folder['id']}");
    }
}


$kernel = new Daemon\Kernel(
    'Migrate elderly School News to Comms',
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {
    $db = Database::getInstance();

    importSchoolNews($db, $log);
    importHomepageNews($db, $log);
});
