<?php

use Alaress\IDatabase;
use Psr\Log\LoggerInterface;


require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

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
        createHomepageNewsArticle($db, $row);
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


$kernel = new Schoolbox\Application\Daemon\Kernel('Migrate elderly School News to Comms', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {
    $db = Database::getInstance();

    /* TODO: Audiencing
     */
    importSchoolNews($db);
    importHomepageNews($db);
});
