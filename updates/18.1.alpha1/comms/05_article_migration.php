<?php

use Alaress\IDatabase;
use Psr\Log\LoggerInterface;
use Schoolbox\Comms\News\Audience\Model;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

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
        createSchoolNewsTopics($db, $articleId, $row, $log);
        createSchoolNewsUserAudience($db, $row['item_id'], $articleId, $log);
        createSchoolNewsCampusRoleAudience($db, $row['item_id'], $articleId, $log);
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
    $mapping = [
        'id' => $row['item_id'],
        'title' => $row['item_heading'],
        'body' => $row['item_body'] ?: '',
        'blurb' => $row['item_blurb'] ?: '',
        'author_id' => $row['item_owner'],
        'feature_image_hash' => $row['item_image'],
        'submitted_at' => $row['item_datetime_modified'],
        'accepted_by' => $row['item_owner'],
        'accepted_at' => $row['item_datetime_modified'],
        'publish_at' => $row['item_datetime_published'],
        'expires_at' => $row['item_datetime_expires'],
        'created_at' => $row['item_datetime_posted'],
        'updated_at' => $row['item_datetime_modified'],
        'deleted_at' => $row['item_deleted'] ? $row['item_datetime_modified'] : null
    ];

    /* If empty, skip adding this thing
     */
    if (!\strlen($row['item_image'])) {
        unset($mapping['feature_image_hash']);
    }

    $keys = implode(', ', array_keys($mapping));

    $params = array_combine(array_map(function($element) {
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
    $log->debug("School News {$row['item_id']} {$row['item_heading']} is now article_id {$lastInsertId}");

    return $lastInsertId;
}

/**
 * Assign topicId to articleId
 *
 * @param IDatabase       $db
 * @param int             $articleId
 * @param array           $row
 * @param LoggerInterface $log
 */
function createSchoolNewsTopics(IDatabase $db, int $articleId, array $row, LoggerInterface $log)
{
    $query = '
        INSERT INTO comms_news_article_topic (article_id, topic_id) 
        VALUES (:articleId, :topicId)
    ';

    $db->prepare($query)->execute([
        'articleId' => $articleId,
        'topicId' => $row['item_topic_id']
    ]);

    /* Log it
     */
    $log->info("School News article_id {$articleId} assigned topic {$row['item_topic_id']}");
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
            VALUES ('{$articleId}', '".Model::ENTITY_TYPE_USER."', '{$user['id']}', '".Model::RELATIONSHIP_SELF."')
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
    $roles = $db->query("
        SELECT gid AS id
        FROM news_items ni
        LEFT JOIN newsGroup ng ON ni.item_id = ng.id
        WHERE ni.item_id = {$oldId}
    ");

    $campuses = $db->query("
        SELECT campus AS id
        FROM news_items ni
        LEFT JOIN newsCampus nc ON ni.item_id = nc.id
        WHERE ni.item_id = {$oldId}
    ");

    foreach ($roles as $role) {
        foreach ($campuses as $campus) {

            /* Create composite
             */
            $query = "
                INSERT INTO `comms_news_audience` (comms_news_article_id, entity_type, relationship)
                VALUES ('{$articleId}', '".Model::ENTITY_TYPE_COMPOSITE."', '".Model::RELATIONSHIP_SELF."');
            ";

            $db->query($query);

            $compositeSegmentId = $db->lastInsertId();

            /* Log it
             */
            $log->info("School News article_id {$articleId} assigned Composite audience {$compositeSegmentId}");

            /* Assign campus
             */
            $query = "
                INSERT INTO `comms_news_audience` (comms_news_article_id, parent_id, entity_type, entity_value, relationship)
                VALUES ('{$articleId}', '{$compositeSegmentId}', '".Model::ENTITY_TYPE_CAMPUS."', '{$campus['id']}', '".Model::RELATIONSHIP_SELF."')
            ";

            $db->query($query);

            /* Log it
             */
            $log->info("School News article_id {$articleId} assigned Campus audience {$campus['id']}");

            /* Assign role
             */
            $query = "
                INSERT INTO `comms_news_audience` (comms_news_article_id, parent_id, entity_type, entity_value, relationship)
                VALUES ('{$articleId}', '{$compositeSegmentId}', '".Model::ENTITY_TYPE_ROLE."', '{$role['id']}', '".Model::RELATIONSHIP_SELF."')
            ";

            $db->query($query);

            /* Log it
             */
            $log->info("School News article_id {$articleId} assigned Role audience {$role['id']}");
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
        createHomepageNewsAudience($db, $row['id'], $articleId, $log);
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
    $mapping = [
        'title' => $row['heading'],
        'body' => $row['body'] ?: '',
        'blurb' => '',
        'author_id' => $row['owner'],
        'submitted_at' => $row['datetime_posted'],
        'accepted_by' => $row['owner'],
        'accepted_at' => $row['datetime_posted'],
        'publish_at' => $row['datetime_published'],
        'expires_at' => $row['datetime_expires'],
        'created_at' => $row['datetime_posted'],
        'updated_at' => $row['datetime_posted'],
    ];

    $keys = implode(', ', array_keys($mapping));

    $params = array_combine(array_map(function($element) {
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
            VALUES ('{$articleId}', '" . Model::ENTITY_TYPE_FOLDER . "', '{$folder['id']}', '" . Model::RELATIONSHIP_SELF . "')
        ");

        /* Log it
         */
        $log->info("Homepage News article_id {$articleId} assigned Folder audience {$folder['id']}");

    }
}


$kernel = new Schoolbox\Application\Daemon\Kernel('Migrate elderly School News to Comms', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {
    $db = Database::getInstance();

    importSchoolNews($db, $log);
    importHomepageNews($db, $log);
});
