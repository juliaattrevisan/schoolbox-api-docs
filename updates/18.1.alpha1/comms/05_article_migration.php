<?php

use Alaress\IDatabase;
use Psr\Log\LoggerInterface;


require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

define('ENTITY_TYPE_COMPOSITE', 1000);
define('ENTITY_TYPE_CAMPUS', 999);
define('ENTITY_TYPE_ROLE', 888);
define('ENTITY_TYPE_USER', 777);
define('RELATIONSHIP_SELF', 456);


/**
 * Import all the School News into Comms
 *
 * @param IDatabase $db
 * @return int
 */
function importSchoolNews(IDatabase $db)
{
    $result = $db->query('
        SELECT * 
        FROM news_items
    ');

    for ($i = 0; $row = $result->fetch(\PDO::FETCH_ASSOC); $i++) {
        $articleId = createSchoolNewsArticle($db, $row);

        createSchoolNewsTopics($db, $articleId, $row);
    }

    return $i;
}


/**
 * Create an article record in the db
 * Return its identifier
 *
 * @param IDatabase $db
 * @param           $row
 * @return string
 */
function createSchoolNewsArticle(IDatabase $db, array $row)
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

    return $db->lastInsertId();
}


/**
 * Assign topicId to articleId
 *
 * @param IDatabase $db
 * @param int       $articleId
 * @param array     $row
 */
function createSchoolNewsTopics(IDatabase $db, int $articleId, array $row)
{
    $query = '
        INSERT INTO comms_news_article_topic (article_id, topic_id) 
        VALUES (:articleId, :topicId)
    ';

    $db->prepare($query)->execute([
        'articleId' => $articleId,
        'topicId' => $row['item_topic_id']
    ]);
}

/**
 * Import all the homepage news too
 *
 * @param IDatabase $db
 * @return int
 */
function importHomepageNews(IDatabase $db)
{
    $result = $db->query('
        SELECT * 
        FROM subject_news
    ');

    for ($i = 0; $row = $result->fetch(\PDO::FETCH_ASSOC); $i++) {
        $articleId = createHomepageNewsArticle($db, $row);

        /* Figger out all the relations, ram them in accordingly
         */
        createHomepageNewsCampusRoleAudience($db, $row['id'], $articleId);
        createHomepageNewsUserAudience($db, $row['id'], $articleId);
    }

    return $i;
}


/**
 * Create an article record in the db
 * Return its identifier
 *
 * @param IDatabase $db
 * @param array     $row
 * @return string
 */
function createHomepageNewsArticle(IDatabase $db, array $row)
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

    return $db->lastInsertId();
}

/**
 * Find out all the Roles for this homepage news
 *
 * @param IDatabase $db
 * @param int       $oldId
 * @param int       $newId
 */
function createHomepageNewsCampusRoleAudience(IDatabase $db, int $oldId, int $newId)
{
    $roles = $db->query("
        SELECT gid AS id
        FROM subject_news sn
        LEFT JOIN newsGroup ng ON sn.id = ng.id
        WHERE sn.id = {$oldId}
    ");

    $campuses = $db->query("
        SELECT campus AS id
        FROM subject_news sn
        LEFT JOIN newsCampus nc ON sn.id = nc.id
        WHERE sn.id = {$oldId}
    ");

    foreach ($roles as $role) {
        foreach ($campuses as $campus) {

            /* Create composite
             */
            $db->query("
                INSERT INTO `comms_news_audience` (article_id, entity_type, relationship)
                VALUES ('{$newId}', ".ENTITY_TYPE_COMPOSITE.", ".RELATIONSHIP_SELF.");
            ");

            $compositeSegmentId = $db->lastInsertId();

            /* Assign campus
             */
            $db->query("
                INSERT INTO `comms_news_audience` (article_id, entity_type, entity_value, relationship)
                VALUES ('{$compositeSegmentId}', ".ENTITY_TYPE_CAMPUS.", '{$campus['id']}', ".RELATIONSHIP_SELF.")
            ");

            /* Assign role
             */
            $db->query("
                INSERT INTO `comms_news_audience` (article_id, entity_type, entity_value, relationship)
                VALUES ('{$compositeSegmentId}', ".ENTITY_TYPE_ROLE.", '{$role['id']}', ".RELATIONSHIP_SELF.")
            ");
        }
    }
}

/**
 * Find out all the Users for this homepage news
 *
 * @param IDatabase $db
 * @param int       $oldId
 * @param int       $newId
 */
function createHomepageNewsUserAudience(IDatabase $db, int $oldId, int $newId)
{
    $users = $db->query("
        SELECT userID AS id
        FROM subject_news sn
        LEFT JOIN newsUser nu ON sn.id = nu.newsID
        WHERE sn.id = {$oldId}
    ");

    foreach ($users as $user) {

        /* Assign user
         */
        $db->query("
            INSERT INTO `comms_news_audience` (article_id, entity_type,  entity_value, relationship)
            VALUES ('{$newId}', ".ENTITY_TYPE_USER.", '{$user['id']}', ".RELATIONSHIP_SELF.")
        ");
    }
}


$kernel = new Schoolbox\Application\Daemon\Kernel('Migrate elderly School News to Comms', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {
    $db = Database::getInstance();

//    importSchoolNews($db);
    importHomepageNews($db);
});
