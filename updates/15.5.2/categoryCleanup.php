<?php
/**
 * Recursively clean up categories that should be deleted categories.
 *
 * All subcategories of deleted categories should be deleted.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

$db     = Database::getInstance();
$loop   = 1;
$max    = 100;
$time   = date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']);
$select = $db->prepare("
    SELECT c.id, c.name
    FROM category c
    INNER JOIN category p on c.parent = p.id
    WHERE c.deleted_at IS NULL
    AND   p.deleted_at IS NOT NULL
");
$delete = $db->prepare("
    UPDATE category
    SET deleted_at = ?
    WHERE id = ?
");
$deletedIds = [];
$logger     = new Alaress\Lumberjack\Logger();
$logger->info('Recursively cleaning up folders that should be deleted');
while ($loop <= $max) {
    $select->execute();
    $deleteCount = $select->rowCount();
    $logger->info(sprintf('Pass %d - found %d non-deleted folders with deleted parents', $loop, $deleteCount));
    if ($deleteCount > 0) {
        foreach ($select as $result) {
            $logger->info(sprintf('Deleting folder #%d (%s)...', $result['id'], $result['name']));
            $delete->execute([$time, $result['id']]);
            $deletedIds[] = $result['id'];
        }
    } else {
        break;
    }
    $loop++;
}

// Tell them how to restore their folders
$logger->info('Cleanup completed!');
if (!empty($deletedIds)) {
    $logger->info('If you need to restore, run the following query:');
    $logger->info('UPDATE category SET deleted_at = NULL WHERE id IN ('.implode(',', $deletedIds).')');
};
